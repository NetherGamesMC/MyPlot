<?php
declare(strict_types=1);

namespace MyPlot\forms\interfaces;

interface ButtonForm{

    public function onButtonClick(): void;
}