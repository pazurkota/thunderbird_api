<?php

namespace App\Repositories;
interface MessageRepositoryInterface
{
    public function saveLastestMessages(array $messages): int;
}