<?php

namespace Tests\Feature;

use App\Support\RuntimeDirectories;
use Tests\TestCase;

class RuntimeDirectoriesTest extends TestCase
{
    public function test_livewire_tmp_and_facade_cache_are_writable(): void
    {
        RuntimeDirectories::ensure();

        foreach (RuntimeDirectories::paths() as $dir) {
            $this->assertDirectoryExists($dir);
            $this->assertTrue(is_writable($dir), $dir.' must be writable so tempnam does not fall back to /tmp.');
        }
    }
}
