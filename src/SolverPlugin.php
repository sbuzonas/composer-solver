<?php

namespace Slbmeh\Composer;

use Composer\Composer;
use Composer\DependencyResolver\Pool;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\AliasPackage;
use Composer\Plugin\PluginInterface;
use Composer\Installer\InstallerEvent;
use Composer\Installer\InstallerEvents;
use Composer\Repository\ArrayRepository;
use Slbmeh\Composer\DependencyResolver\SolverFactory;

class SolverPlugin implements PluginInterface, EventSubscriberInterface
{

    protected $composer;
    protected $io;
    protected $repository;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->repository = new ArrayRepository();
    }

    public static function getSubscribedEvents()
    {
        return array(
            InstallerEvents::PRE_DEPENDENCIES_SOLVING => array(
                array('beforeDependenciesSolving', 0)
            ),
        );
    }

    public function beforeDependenciesSolving(InstallerEvent $event)
    {
        $solver = SolverFactory::createFromInstallEvent($event);
        $results = $solver->solve($event->getRequest());
        foreach ($results as $name => $package) {
            $this->propagateSolution($name, $package);
        }
        $pool = $event->getPool();
        $this->resetPool($pool);
        $pool->addRepository($this->repository);
    }

    protected function propagateSolution($package, $result)
    {
        $package = new AliasPackage($result, $result->getVersion(), $result->getPrettyVersion());
        $this->repository->addPackage($package);
    }

    protected function resetPool(Pool $pool)
    {
        $reflector = new \ReflectionObject($pool);
        $properties = array(
            'repositories',
            'providerRepos',
            'packages',
            'packageByName',
            'packageByExactName',
            'providerCache'
        );
        
        foreach ($properties as $property) {
            $propertyReflector = $reflector->getProperty($property);
            $propertyReflector->setAccessible(true);
            $propertyReflector->setValue($pool, array());
        }
    }
}
