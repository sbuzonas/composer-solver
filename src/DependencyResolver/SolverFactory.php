<?php

namespace Slbmeh\Composer\DependencyResolver;

use Composer\Installer\InstallerEvent;

class SolverFactory
{
    public static function createFromInstallEvent(InstallerEvent $event)
    {
        $policy = $event->getPolicy();
        $policyReflector = new \ReflectionObject($policy);
        $stableReflector = $policyReflector->getProperty('preferStable');
        $stableReflector->setAccessible(true);
        $lowestReflector = $policyReflector->getProperty('preferLowest');
        $lowestReflector->setAccessible(true);

        $preferStable = $stableReflector->getValue($policy);
        $preferLowest = $lowestReflector->getValue($policy);
        
        if ($event->getIO()->isDebug()) {
            $queue = new DebugQueue($event->getPool(), $preferStable, $preferLowest);
            $queue->setIO($event->getIO());
        } else {
            $queue = new RequestQueue($event->getPool(), $preferStable, $preferLowest);
        }
        $solver = new Solver($queue, $event->getInstalledRepo());

        return $solver;
    }
}
