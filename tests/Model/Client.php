<?php

declare(strict_types=1);

namespace atk4\data\tests\Model;

class Client extends User
{
    public function init(): void
    {
        parent::init();

        $this->addField('order', ['default' => '10']);
    }
}
