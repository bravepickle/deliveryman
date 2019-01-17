<?php
/**
 * Date: 2019-01-02
 * Time: 18:40
 */

namespace DeliverymanTest\Service;

use Deliveryman\Channel\HttpGraphChannel;
use Deliveryman\Entity\BatchRequest;
use Deliveryman\Entity\HttpGraph\ChannelConfig;
use Deliveryman\Entity\HttpGraph\HttpRequest;
use Deliveryman\Entity\HttpGraph\HttpHeader;
use Deliveryman\Entity\RequestConfig;
use Deliveryman\EventListener\Event;
use Deliveryman\Service\BatchRequestValidator;
use Deliveryman\Service\ConfigManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\ConstraintViolationList;

class BatchRequestValidatorTest extends TestCase
{
    /**
     * @dataProvider validateProvider
     * @param BatchRequest $batchRequest
     * @param array $expected
     */
    public function testValidate(BatchRequest $batchRequest, array $expected)
    {
        $configManager = new ConfigManager();
        $dispatcher = new EventDispatcher();
        $batchValidator = new BatchRequestValidator($configManager, $dispatcher);

        $dispatcher->addListener('deliveryman.validation.before', function ($event, $action) {
            /** @var Event $event */
            $this->assertEquals('deliveryman.validation.before', $action);
            $this->assertInstanceOf(Event::class, $event);
            $this->assertInstanceOf(BatchRequest::class, $event->getData());
        });

        $dispatcher->addListener('deliveryman.validation.after', function ($event, $action) {
            /** @var Event $event */
            $this->assertEquals('deliveryman.validation.after', $action);
            $this->assertInstanceOf(Event::class, $event);
            $this->assertInstanceOf(ConstraintViolationList::class, $event->getData());
        });

        $actual = $batchValidator->validate($batchRequest, ['Default', HttpGraphChannel::NAME]);

        $this->assertEquals($expected, $actual);
    }

    public function validateProvider()
    {
        return [
            [
                'input' => (new BatchRequest()),
                'expected' => [
                    'data' => ['This value should not be blank.'],
                ],
            ],
            [
                'input' => (new BatchRequest())
                    ->setConfig((new RequestConfig())
                        ->setOnFail('foo-fail')
                        ->setFormat('foo-format')
                        ->setConfigMerge('foo-merge')
                        ->setSilent('foo-silent')
                    )
                ,
                'expected' => [
                    'config.configMerge' => ['The value you selected is not a valid choice.',],
                    'config.onFail' => ['The value you selected is not a valid choice.',],
                    'config.format' => ['The value you selected is not a valid choice.',],
                    'data' => ['This value should not be blank.'],
                ],
            ],
            [
                'input' => (new BatchRequest())
                    ->setConfig((new RequestConfig())
                        ->setOnFail('abort-queue')
                        ->setFormat('json')
                        ->setConfigMerge('first')
                        ->setSilent(true)
                        ->setChannel((new ChannelConfig())
                            ->setExpectedStatusCodes(['bad-status-code'])
                        )
                    )
                ,
                'expected' => [
                    'config.channel.expectedStatusCodes[0]' => ['This value should be of type integer.',],
                    'data' => ['This value should not be blank.'],
                ],
            ],
            [
                'input' => (new BatchRequest())
                    ->setData(['wrong input', null, false, 0, 1, [234],
                        (new HttpRequest())->setId('uniq')->setUri('http://localhost/'),
                        new \stdClass])
                ,
                'expected' => [
                    'data[0]' => ['Expecting to have HTTP request. Received "string".',],
                    'data[1]' => ['Expecting to have HTTP request. Received "NULL".',],
                    'data[2]' => ['Expecting to have HTTP request. Received "boolean".',],
                    'data[3]' => ['Expecting to have HTTP request. Received "integer".',],
                    'data[4]' => ['Expecting to have HTTP request. Received "integer".',],
                    'data[5]' => ['Expecting to have HTTP request. Received "array".',],
                    'data[7]' => ['Expecting to have HTTP request. Received "object".',],
                ],
            ],
            [
                'input' => (new BatchRequest())
                    ->setData([
                        (new HttpRequest())->setMethod('BAD'), // no ID, no URI, incorrect method
                        (new HttpRequest())->setUri('http://example.com/1')->setId('dup'),
                        (new HttpRequest())->setUri('http://example.com/2')
                            ->setHeaders([ // incorrect headers
                                (new HttpHeader())->setName(null)->setValue('whatever'),
                                (new HttpHeader())->setName('X-Ok')->setValue(false),
                            ])
                            ->setId('dup'), // duplicate, wrong headers format
                        (new HttpRequest())->setUri('http://example.com/3')->setReq(['dup', 'unknown']), // undefined reference
                    ])
                ,
                'expected' => [
                    'data[0].method' => ['The value you selected is not a valid choice.',],
                    'data[0].uri' => ['This value should not be blank.',],
                    'data[1].id' => ['HTTP request ID "dup" is ambiguous.',],
                    'data[2].headers[0].name' => ['This value should not be blank.',],
                    'data[2].headers[1].value' => ['This value should be of type string.',],
                    'data[3].req[1]' => ['HTTP request reference by ID "unknown" does not exist.',],
                ],
            ],
        ];
    }
}
