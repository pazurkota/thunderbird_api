<?php

namespace App\Services;

class EnvService
{
    private string $envFilePath;

    public function __construct(string $envFilePath)
    {
        $this->envFilePath = $envFilePath;
    }

    public function updateKey(string $key, string $value): void
    {
        if (!file_exists($this->envFilePath)) {
            file_put_contents($this->envFilePath, "{$key}={$value}\n");
            return;
        }

        $envContent = file_get_contents($this->envFilePath);
        $pattern = "/^{$key}=.*/m";

        if (preg_match($pattern, $envContent)) {
            $envContent = preg_replace($pattern, "{$key}={$value}", $envContent);
        } else {
            $envContent .= "\n{$key}={$value}\n";
        }

        file_put_contents($this->envFilePath, trim($envContent) . "\n");
    }
}