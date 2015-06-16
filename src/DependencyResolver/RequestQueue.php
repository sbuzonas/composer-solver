<?php

namespace Slbmeh\Composer\DependencyResolver;

use Composer\DependencyResolver\PolicyInterface;
use Composer\DependencyResolver\Pool;
use Composer\Package\AliasPackage;
use Composer\Package\BasePackage;
use Composer\Package\PackageInterface;
use Composer\Package\LinkConstraint\VersionConstraint;
use Composer\Package\Version\VersionSelector;
use Composer\Repository\ArrayRepository;

class RequestQueue
{
    protected $pool;
    protected $preferStable;
    protected $preferLowest;
    protected $jobs = array();
    protected $resolved = array();
    protected $candidates = array();

    public function __construct(Pool $pool, $preferStable = false, $preferLowest = false)
    {
        $this->pool   = $pool;
        $this->preferStable = $preferStable;
        $this->preferLowest = $preferLowest;
    }

    public function addJob(Job $job)
    {
        if (!$this->hasJob($job)) {
            $this->jobs[] = $job;
        
            if (empty($packages = $this->pool->whatProvides($job->getPackageName(), $job->getConstraint()))) {
                var_dump($job->getPackageName(), $job->getConstraint(), $packages); exit;
                throw new \Exception('could not be found');
            }

            if (1 < count($packages)) {
                if (!$this->addCandidates($job->getPackageName(), $packages)) {
                    throw new \Exception('all candidates conflict');
                }
                $this->findSolvedCandidates();
            } else {
                $package = array_shift($packages);
                $this->addSolution($job->getPackageName(), $package);
            }
        }
    }

    public function getJobs()
    {
        return $this->jobs;
    }

    public function hasJob(Job $targetJob)
    {
        foreach ($this->jobs as $job) {
            if ($job === $targetJob) {
                return true;
            }
            if ($targetJob->getPackageName() === $job->getPackageName()) {
                if ($targetJob->getConstraint()->matches($job->getConstraint())) {
                    return true;
                }
            }
        }
        
        return false;
    }

    public function resolve()
    {
        $this->findSolvedCandidates();
        foreach ($this->candidates as $candidate => $packages) {
            if (0 === count($packages)) {
                throw new \Exception('no candidates to satisfy '. $candidate);
            }
        }
        while (!empty($this->candidates)) {
            $candidates = $this->candidates;
            uasort($candidates, function($a, $b) {
                if (count($a) === count($b)) {
                    return 0;
                }

                return count($a) > count($b) ? 1 : -1;
            });
        
            $queue = clone $this;
            try {
                foreach ($candidates as $packageName => $packages) {
                    if ($this->preferStable) {
                        $stabilityMap = array();
                        foreach ($packages as $package) {
                            $stabilityMap[$package->getStability()][] = $package;
                        }

                        uksort($stabilityMap, function ($a, $b) {
                            return BasePackage::$stabilities[$a] > BasePackage::$stabilities[$b];
                        });

                        $packages = array_shift($stabilityMap);
                    }

                    $preferLowest = $this->preferLowest;
                    usort($packages, function ($a, $b) use ($packageName, $preferLowest) {
                            if ($a->getName() !== $b->getName()) {
                                if ($packageName === $a->getName()) {
                                    return -1;
                                } elseif ($packageName === $b->getName()) {
                                    return 1;
                                }
                            }
                        
                            if (version_compare($a->getVersion(), $b->getVersion(), '=')) {
                                return 0;
                            }

                            $comparison = $preferLowest ? '>': '<';

                            return version_compare($a->getVersion(), $b->getVersion(), $comparison);
                        });


                    $package = array_shift($packages);

                    $queue->addSolution($packageName, $package);
                }
                $solutions = $queue->resolve();
                foreach ($solutions as $name => $solution) {
                    $this->addSolution($name, $solution);
                }
            } catch (\Exception $e) {
                var_dump($e);
                // remove candidate
                throw new \Exception('solution mismatch');
            }
        }
        
        return $this->resolved;
    }

    public function addSolution($packageName, PackageInterface $package)
    {
        if (!array_key_exists($packageName, $this->resolved)) {
            $this->removeCandidates($packageName);
            $this->removeConflictingCandidates($package);
            $this->resolved[$packageName] = $package;
            $this->addJob(new Job('install', $packageName, new VersionConstraint('=', $package->getVersion())));
            if ($packageName === $package->getName()) {
                $additionalPackages = array_merge($package->getProvides(), $package->getRequires());
                foreach ($additionalPackages as $provided) {
                    $this->addJob(new Job('install', $provided->getTarget(), $provided->getConstraint()));
                }
            }
            foreach ($package->getRequires() as $requiredPackage) {
                foreach ($this->resolved as $solution) {
                    if ($requiredPackage->getTarget() === $solution->getName()) {
                        continue 2;
                    }
                }
                $this->addJob(new Job('install', $requiredPackage->getTarget(), $requiredPackage->getConstraint()));
            }
        }
    }

    protected function addCandidates($packageName, array $packages)
    {
        if (!$hasPackage = array_key_exists($packageName, $this->candidates)) {
            $this->candidates[$packageName] = array();
        }
        foreach ($packages as $package) {
            foreach ($this->resolved as $solution) {
                if ($this->conflicts($solution, $package)) {
                    continue 2;
                }
            }
            $hasPackage = true;
            $hasCandidate = false;
            foreach ($this->candidates[$packageName] as $candidate) {
                if ($candidate === $package) {
                    $hasCandidate = true;
                    break;
                }
            }
            if (!$hasCandidate) {
                $this->candidates[$packageName][] = $package;
            }
        }

        return $hasPackage;
    }

    protected function getCandidates($packageName)
    {
        return $this->candidates[$packageName];
    }

    protected function removeCandidates($packageName)
    {
        if (array_key_exists($packageName, $this->candidates)) {
            unset($this->candidates[$packageName]);
        }
    }

    protected function removeConflictingCandidates(PackageInterface $package)
    {
        if (!empty($this->candidates)) {
            $newCandidates = array();
            foreach ($this->candidates as $packageName => $candidates) {
                if ($packageName === $package->getName()) {
                    continue;
                }
                $newCandidates[$packageName] = array();
                foreach ($candidates as $candidate) {
                    if (!$this->conflicts($package, $candidate)) {
                        $newCandidates[$packageName][] = $candidate;
                    }
                }
                if (empty($newCandidates[$packageName])) {
                    throw new \Exception('no candidates left to install');
                }
            }
            $this->candidates = $newCandidates;
            $this->findSolvedCandidates();
        }
    }

    protected function findSolvedCandidates()
    {
        foreach ($this->candidates as $packageName => $packages) {
            if (1 === count($packages)) {
                $this->addSolution($packageName, current($packages));
            }
        }
    }

    protected function conflicts(PackageInterface $source, PackageInterface $target)
    {
        $possibleConflicts = array_merge($source->getConflicts(), $source->getReplaces(), $source->getProvides());
        if (!empty($possibleConflicts)) {
            foreach ($possibleConflicts as $link) {
                if ($link->getTarget() !== $target->getName()) {
                    continue;
                }
                $constraint = new VersionConstraint('=', $target->getVersion());
                if ($link->getConstraint()->matches($constraint)) {
                    return true;
                }
            }
        }
        return false;
    }
}
