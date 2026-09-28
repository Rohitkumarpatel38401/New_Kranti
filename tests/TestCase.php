<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Every production request gets fresh scoped instances (such as the current institute).
     * Tests reuse one application for all requests, so drop them after each request too.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);

        $this->app->forgetScopedInstances();

        return $response;
    }
}
