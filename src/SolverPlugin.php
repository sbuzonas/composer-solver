<?php

namespace Slbmeh\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Installer\InstallerEvent;
use Composer\Installer\InstallerEvents;

class SolverPlugin implements PluginInterface, EventSubscriberInterface
{

    protected $composer;
    protected $io;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
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
        //        var_dump($event); exit;
    }
}
