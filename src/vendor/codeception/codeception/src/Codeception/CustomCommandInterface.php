<?php

namespace Codeception;

interface CustomCommandInterface
{
    /**
     * returns the name of the command
     */
    public static function getCommandName(): string;
}
