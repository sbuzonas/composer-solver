<?php

namespace Slbmeh\Composer\DependencyResolver;

use Composer\DependencyResolver\PolicyInterface;
use Composer\DependencyResolver\Pool;
use Composer\DependencyResolver\Request;
use Composer\Repository\RepositoryInterface;

class Solver
{
    protected $policy;
    protected $installedRepo;
    protected $queue;

    public function __construct(
        RequestQueue $queue,
        RepositoryInterface $installedRepo = null
    ) {
        $this->queue         = $queue;
        $this->installedRepo = $installedRepo ?: new CompositeRepository();
    }

    public function solve(Request $request)
    {
        foreach ($request->getJobs() as $job) {
            switch ($job['cmd']) {
                case 'install':
                case 'update':
                    $command = $job['cmd'];
                    $package = $job['packageName'];
                    $constraint = $job['constraint'];
                    $this->queue->addJob(new Job($command, $package, $constraint));
                    break;
                default:
                    var_dump($job);
            }
        }
        return $this->queue->resolve();
    }
}
