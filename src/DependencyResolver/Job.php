<?php

namespace Slbmeh\Composer\DependencyResolver;

use Composer\Package\LinkConstraint\LinkConstraintInterface;

class Job
{

    protected $command;
    protected $constraint;
    protected $packageName;

    public function __construct($command, $packageName, LinkConstraintInterface $constraint)
    {
        $this->command     = $command;
        $this->packageName = $packageName;
        $this->constraint  = $constraint;
    }

    public function getCommand()
    {
        return $this->command;
    }

    public function getPackageName()
    {
        return $this->packageName;
    }

    public function getConstraint()
    {
        return $this->constraint;
    }
}
