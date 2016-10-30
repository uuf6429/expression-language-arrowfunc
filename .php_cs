<?php

return Symfony\CS\Config\Config::create()
    ->level(Symfony\CS\FixerInterface::SYMFONY_LEVEL)
    ->finder(
        Symfony\CS\Finder\DefaultFinder::create()
            ->exclude(['vendor'])
            ->in(__DIR__)
    )
;
