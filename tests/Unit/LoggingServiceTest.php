<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Services\LoggingService;
use Illuminate\Support\Facades\Log;

describe('LoggingService', function () {
    beforeEach(function () {
        $this->loggingService = new LoggingService();

        // Mock the Log facade
        Log::shouldReceive('channel')->andReturnSelf()->byDefault();
        Log::shouldReceive('info')->andReturnSelf()->byDefault();
        Log::shouldReceive('debug')->andReturnSelf()->byDefault();
        Log::shouldReceive('warning')->andReturnSelf()->byDefault();
        Log::shouldReceive('error')->andReturnSelf()->byDefault();
        Log::shouldReceive('log')->andReturnSelf()->byDefault();
        Log::shouldReceive('log')->andReturnSelf()->byDefault();
    });

    describe('basic logging operations', function () {
        it('can log operation start and end', function () {
            Log::shouldReceive('channel')
                ->with('modelschema')
                ->andReturnSelf()
                ->twice();

            Log::shouldReceive('info')
                ->with(Mockery::pattern('/🚀 Starting test_operation/'), Mockery::any())
                ->once();

            Log::shouldReceive('info')
                ->with(Mockery::pattern('/✅ Completed test_operation/'), Mockery::any())
                ->once();

            $this->loggingService->logOperationStart('test_operation', ['param' => 'value']);
            $this->loggingService->logOperationEnd('test_operation', ['result' => 'success']);
        });

        it('includes session id in all logs', function () {
            Log::shouldReceive('channel')
                ->with('modelschema')
                ->andReturnSelf();

            Log::shouldReceive('info')
                ->with(
                    Mockery::any(),
                    Mockery::on(function ($data) {
                        return isset($data['session_id']) && is_string($data['session_id']);
                    })
                )
                ->once();

            $this->loggingService->logOperationStart('test');
        });

        it('tracks memory usage', function () {
            Log::shouldReceive('channel')
                ->andReturnSelf();

            Log::shouldReceive('info')
                ->with(
                    Mockery::any(),
                    Mockery::on(function ($data) {
                        return isset($data['memory_usage']) &&
                               preg_match('/\d+\.?\d* (B|KB|MB|GB)/', $data['memory_usage']);
                    })
                )
                ->once();

            $this->loggingService->logOperationStart('test');
        });
    });

    describe('debug logging', function () {
        it('logs debug information with context', function () {
            Log::shouldReceive('channel')
                ->with('modelschema')
                ->andReturnSelf();

            Log::shouldReceive('debug')
                ->with(
                    '🔍 Debug message',
                    Mockery::on(function ($data) {
                        return isset($data['session_id']) &&
                               isset($data['data']) &&
                               $data['data']['test'] === 'value';
                    })
                )
                ->once();

            $this->loggingService->logDebug('Debug message', ['test' => 'value']);
        });
    });

    describe('warning logging', function () {
        it('logs warnings with recommendations', function () {
            Log::shouldReceive('channel')
                ->with('modelschema')
                ->andReturnSelf();

            Log::shouldReceive('warning')
                ->with(
                    '⚠️ Warning message',
                    Mockery::on(function ($data) {
                        return isset($data['recommendation']) &&
                               $data['recommendation'] === 'Do something';
                    })
                )
                ->once();

            $this->loggingService->logWarning(
                'Warning message',
                ['context' => 'test'],
                'Do something'
            );
        });
    });

    describe('error logging', function () {
        it('logs errors with exception details', function () {
            $exception = new Exception('Test error', 123);

            Log::shouldReceive('channel')
                ->with('modelschema')
                ->andReturnSelf();

            Log::shouldReceive('error')
                ->with(
                    '❌ Error occurred',
                    Mockery::on(function ($data) {
                        return isset($data['exception']) &&
                               $data['exception']['class'] === Exception::class &&
                               $data['exception']['message'] === 'Test error' &&
                               isset($data['exception']['trace']);
                    })
                )
                ->once();

            $this->loggingService->logError('Error occurred', $exception);
        });
    });

    describe('performance logging', function () {
        it('logs performance metrics', function () {
            Log::shouldReceive('channel')
                ->with('modelschema')
                ->andReturnSelf();

            Log::shouldReceive('info')
                ->with(
                    '📊 Performance: test_operation',
                    Mockery::on(function ($data) {
                        return isset($data['metrics']) &&
                               $data['metrics']['duration'] === 100 &&
                               isset($data['session_duration_ms']);
                    })
                )
                ->once();

            $this->loggingService->logPerformance('test_operation', [
                'duration' => 100,
                'memory' => '50MB',
            ]);
        });
    });

    describe('validation logging', function () {
        it('logs successful validation', function () {
            Log::shouldReceive('channel')
                ->with('modelschema')
                ->andReturnSelf();

            Log::shouldReceive('log')
                ->with(
                    'info',
                    '✅ Validation schema: Passed',
                    Mockery::on(function ($data) {
                        return $data['success'] === true &&
                               $data['error_count'] === 0 &&
                               $data['warning_count'] === 0;
                    })
                )
                ->once();

            $this->loggingService->logValidation('schema', true, [], [], ['fields' => 5]);
        });

        it('logs failed validation with errors', function () {
            Log::shouldReceive('channel')
                ->with('modelschema')
                ->andReturnSelf();

            Log::shouldReceive('log')
                ->with(
                    'error',
                    '❌ Validation schema: Failed',
                    Mockery::on(function ($data) {
                        return $data['success'] === false &&
                               $data['error_count'] === 2 &&
                               count($data['errors']) === 2;
                    })
                )
                ->once();

            $this->loggingService->logValidation(
                'schema',
                false,
                ['Error 1', 'Error 2'],
                ['Warning 1']
            );
        });
    });

    describe('generation logging', function () {
        it('logs successful generation', function () {
            Log::shouldReceive('channel')
                ->with('modelschema')
                ->andReturnSelf();

            Log::shouldReceive('info')
                ->with(
                    '🎯 Generation model -> User: Success',
                    Mockery::on(function ($data) {
                        return $data['generation_type'] === 'model' &&
                               $data['target'] === 'User' &&
                               $data['success'] === true;
                    })
                )
                ->once();

            $this->loggingService->logGeneration('model', 'User', true, ['size' => 1024]);
        });
    });

    describe('YAML parsing logging', function () {
        it('logs YAML parsing results', function () {
            Log::shouldReceive('channel')
                ->with('modelschema')
                ->andReturnSelf();

            Log::shouldReceive('info')
                ->with(
                    '📄 YAML Parsing: user.schema.yml',
                    Mockery::on(function ($data) {
                        return $data['source'] === 'user.schema.yml' &&
                               $data['success'] === true &&
                               isset($data['statistics']);
                    })
                )
                ->once();

            $this->loggingService->logYamlParsing(
                'user.schema.yml',
                true,
                ['parse_time' => 50, 'fields' => 10]
            );
        });
    });

    describe('cache logging', function () {
        it('logs cache operations', function () {
            Log::shouldReceive('channel')
                ->with('modelschema')
                ->andReturnSelf();

            Log::shouldReceive('debug')
                ->with(
                    '🎯 Cache hit: schema:user',
                    Mockery::on(function ($data) {
                        return $data['cache_operation'] === 'hit' &&
                               $data['cache_key'] === 'schema:user' &&
                               $data['cache_hit'] === true;
                    })
                )
                ->once();

            $this->loggingService->logCache('hit', 'schema:user', true, 0.001);
        });
    });

    describe('configuration', function () {
        it('can be enabled and disabled', function () {
            expect($this->loggingService->isEnabled())->toBeTrue();

            $this->loggingService->setEnabled(false);
            expect($this->loggingService->isEnabled())->toBeFalse();
        });

        it('does not log when disabled', function () {
            Log::shouldNotReceive('channel');
            Log::shouldNotReceive('info');

            $this->loggingService->setEnabled(false);
            $this->loggingService->logOperationStart('test');
        });

        it('generates unique session ids', function () {
            $service1 = new LoggingService();
            $service2 = new LoggingService();

            expect($service1->getSessionId())->not->toBe($service2->getSessionId());
        });
    });

    describe('context tracking', function () {
        it('tracks operation context stack', function () {
            Log::shouldReceive('channel')->andReturnSelf();
            Log::shouldReceive('info')->twice();
            Log::shouldReceive('debug')->once();

            $this->loggingService->logOperationStart('outer_operation');
            $this->loggingService->logOperationStart('inner_operation');

            Log::shouldReceive('debug')
                ->with(
                    Mockery::any(),
                    Mockery::on(function ($data) {
                        return isset($data['context_stack']) &&
                               count($data['context_stack']) === 2 &&
                               in_array('outer_operation', $data['context_stack']) &&
                               in_array('inner_operation', $data['context_stack']);
                    })
                );

            $this->loggingService->logDebug('Test debug with context');
        });
    });
});
