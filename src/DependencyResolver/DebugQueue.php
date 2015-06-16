<?php

namespace Slbmeh\Composer\DependencyResolver;

use Composer\DependencyResolver\PolicyInterface;
use Composer\DependencyResolver\Pool;
use Composer\Package\PackageInterface;
use Composer\IO\IOInterface;

class DebugQueue extends RequestQueue
{

    protected $io;

    public function setIO(IOInterface $io)
    {
        $this->io = $io;
    }

    public function addJob(Job $job)
    {
        if (!$this->hasJob($job)) {
            $this->io->writeError(sprintf('Adding job: <info>%s</info> (<comment>%s</comment>)', $job->getPackageName(), $job->getConstraint()->getPrettyString()));
        }
        return parent::addJob($job);
    }

    public function addSolution($packageName, PackageInterface $package)
    {
        if (!array_key_exists($packageName, $this->resolved)) {
            $this->io->writeError(sprintf('Adding solution: <info>%s</info> (<comment>%s</comment>)', $package->getPrettyName(), $package->getVersion()));
        }
        return parent::addSolution($packageName, $package);
    }

    protected function addCandidates($packageName, array $packages)
    {
        $candidateCount = 0;
        if (array_key_exists($packageName, $this->candidates)) {
            $candidateCount = count($this->candidates[$packageName]);
        }
        $ret = parent::addCandidates($packageName, $packages);

        if ($finalCount = count($this->candidates[$packageName]) - $candidateCount) {
            $this->io->writeError(sprintf('Adding <comment>%d</comment> candidates for <info>%s</info>', $finalCount, $packageName));
        }
        
        return $ret;
    }

    protected function removeCandidates($packageName)
    {
        if (array_key_exists($packageName, $this->candidates)) {
            $this->io->writeError(sprintf('Removed <comment>%d</comment> candidates for <info>%s</info>', count($this->candidates[$packageName]), $packageName));
        }

        return parent::removeCandidates($packageName);
    }

    protected function removeConflictingCandidates(PackageInterface $package)
    {
        $initialCount = $this->getNumberOfCandidates();
        $ret = parent::removeConflictingCandidates($package);
        if ($initialCount) {
            $removed = $initialCount - $this->getNumberOfCandidates();
            if ($removed) {
                $this->io->writeError(sprintf('Removed <comment>%d</comment> conflicting candidates.', $removed));
            }
        }
        
        return $ret;
    }

    protected function getNumberOfCandidates()
    {
        $total = 0;
        if (!empty($this->candidates)) {
            foreach ($this->candidates as $candidate => $packages) {
                $total += count($packages);
            }
        }

        return $total;
    }
}
