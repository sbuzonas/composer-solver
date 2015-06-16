<?php

namespace Slbmeh\Composer\DependencyResolver;

use Composer\IO\IOInterface;

class DebugSolver extends Solver
{

    protected $io;

    public function setIO(IOInterface $io)
    {
        $this->io = $io;
    }

    public function addJob(Job $job)
    {
        $this->io->writeError(sprintf('<comment>Adding job:</comment> %s', $job->prettyPrint()));
        return parent::addJob($job);
    }

    protected function addSolution($packageName, PackageInterface $package)
    {
        $this->io->writeError(sprintf('<comment>Adding solution:</comment> %s (%s)', $package->getPrettyName(), $package->getVersion()));
        return parent::addSolution($packageName, $package);
    }
}
