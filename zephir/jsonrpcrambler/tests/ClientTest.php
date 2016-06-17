<?php

namespace JsonRpcRambler\Tests;

use JsonRpcRambler\Client;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    public function testParseReponse()
    {
        $client = new Client('http://localhost/');

        $this->assertEquals(
            -19,
            $client->parseResponse(json_decode('{"jsonrpc": "2.0", "result": -19, "id": 1}', true))
        );

        $this->assertEquals(
            null,
            $client->parseResponse(json_decode('{"jsonrpc": "2.0", "id": 1}', true))
        );
    }

    /**
     * @expectedException \BadFunctionCallException
     */
    public function testBadProcedure()
    {
        $client = new Client('http://localhost/');
        $client->parseResponse(
            json_decode('{"jsonrpc": "2.0", "error": {"code": -32601, "message": "Method not found"}, "id": "1"}', true)
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidArgs()
    {
        $client = new Client('http://localhost/');
        $client->parseResponse(
            json_decode('{"jsonrpc": "2.0", "error": {"code": -32602, "message": "Invalid params"}, "id": "1"}', true)
        );
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testInvalidRequest()
    {
        $client = new Client('http://localhost/');
        $client->parseResponse(
            json_decode('{"jsonrpc": "2.0", "error": {"code": -32600, "message": "Invalid Request"}, "id": null}', true)
        );
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testParseError()
    {
        $client = new Client('http://localhost/');
        $client->parseResponse(
            json_decode('{"jsonrpc": "2.0", "error": {"code": -32700, "message": "Parse error"}, "id": null}', true)
        );
    }

    /**
     * @expectedException \JsonRpcRambler\Exceptions\ServerErrorException
     */
    public function testServerError()
    {
        $client = new Client('http://localhost/');
        $client->handleHttpErrors(
            ['HTTP/1.0 301 Moved Permantenly', 'Connection: close', 'HTTP/1.1 500 Internal Server Error']
        );
    }

    /**
     * @expectedException \JsonRpcRambler\Exceptions\ConnectionFailureException
     */
    public function testBadUrl()
    {
        $client = new Client('http://something_not_found/');
        $client->execute('plop');
    }

    /**
     * @expectedException \JsonRpcRambler\Exceptions\ConnectionFailureException
     */
    public function test404()
    {
        $client = new Client('http://localhost/');
        $client->handleHttpErrors(['HTTP/1.1 404 Not Found']);
    }

    /**
     * @expectedException \JsonRpcRambler\Exceptions\AccessDeniedException
     */
    public function testAccessForbiddenError()
    {
        $client = new Client('http://localhost/');
        $client->handleHttpErrors(['HTTP/1.0 301 Moved Permantenly', 'Connection: close', 'HTTP/1.1 403 Forbidden']);
    }

    /**
     * @expectedException \JsonRpcRambler\Exceptions\AccessDeniedException
     */
    public function testAccessNotAllowedError()
    {
        $client = new Client('http://localhost/');
        $client->handleHttpErrors(['HTTP/1.0 301 Moved Permantenly', 'Connection: close', 'HTTP/1.0 401 Unauthorized']);
    }

    public function testSuppressException()
    {
        $client = new Client('http://localhost/', 3, [], true);
        $exception = $client->parseResponse(
            json_decode('{"jsonrpc": "2.0", "error": {"code": -32600, "message": "Invalid Request"}, "id": null}', true)
        );
        $this->assertInstanceOf('\RuntimeException', $exception);
    }

    public function testPrepareRequest()
    {
        $client = new Client('http://localhost/');

        $payload = $client->prepareRequest('myProcedure');
        $this->assertNotEmpty($payload);
        $this->assertArrayHasKey('jsonrpc', $payload);
        $this->assertEquals('2.0', $payload['jsonrpc']);
        $this->assertArrayHasKey('method', $payload);
        $this->assertEquals('myProcedure', $payload['method']);
        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayNotHasKey('params', $payload);

        $payload = $client->prepareRequest('myProcedure', ['p1' => 3]);
        $this->assertNotEmpty($payload);
        $this->assertArrayHasKey('jsonrpc', $payload);
        $this->assertEquals('2.0', $payload['jsonrpc']);
        $this->assertArrayHasKey('method', $payload);
        $this->assertEquals('myProcedure', $payload['method']);
        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('params', $payload);
        $this->assertEquals(['p1' => 3], $payload['params']);
    }

    public function testBatchRequest()
    {
        $client = new Client('http://localhost/');

        $batch = $client->batch();

        $this->assertInstanceOf('JsonRpcRambler\\Client', $batch);
        $this->assertTrue($client->isBatch);

        $batch->execute('foo', ['p1' => 42, 'p3' => 3]);

        $this->assertNotEmpty($client->batch);
        $this->assertEquals(1, count($client->batch));

        $this->assertEquals('foo', $client->batch[0]['method']);

        $this->assertEquals(['p1' => 42, 'p3' => 3], $client->batch[0]['params']);

        $batch = $client->batch();

        $this->assertInstanceOf('\JsonRpcRambler\Client', $batch);
        $this->assertTrue($client->isBatch);
        $this->assertEmpty($client->batch);
    }
}