<?php

namespace TCCL\Test\Router\Test;

use TCCL\Router\PayloadVerify;
use TCCL\Router\PayloadVerifyException;
use TCCL\Test\Router\RouterTestCase;

final class PayloadVerifyTest extends RouterTestCase {
    public function testTypeBoolean() {
        $payload = [
            'accept' => true,
        ];

        $format = [
            'accept' => 'b',
        ];

        try {
            PayloadVerify::verify($payload,$format);
            $status = true;
        } catch (PayloadVerifyException $ex) {
            $status = false;
        }
        $this->assertTrue($status);

        $this->expectException(PayloadVerifyException::class);

        $payloadBad = [
            'accept' => 1,
        ];
        PayloadVerify::verify($payloadBad,$format);
    }

    public function testTypeString() {
        $payload = [
            'name' => 'Roger',
        ];

        $format = [
            'name' => 's',
        ];

        try {
            PayloadVerify::verify($payload,$format);
            $status = true;
        } catch (PayloadVerifyException $ex) {
            $status = false;
        }
        $this->assertTrue($status);

        $this->expectException(PayloadVerifyException::class);

        $payloadBad = [
            'name' => 33,
        ];
        PayloadVerify::verify($payloadBad,$format);
    }

    public function testTypeInteger() {
        $payload = [
            'age' => 25,
        ];

        $format = [
            'age' => 'i',
        ];

        try {
            PayloadVerify::verify($payload,$format);
            $status = true;
        } catch (PayloadVerifyException $ex) {
            $status = false;
        }
        $this->assertTrue($status);

        $this->expectException(PayloadVerifyException::class);

        $payloadBad = [
            'age' => '25',
        ];
        PayloadVerify::verify($payloadBad,$format);
    }

    public function testTypeFloat() {
        $payload = [
            'ratio' => 0.87345,
        ];

        $format = [
            'ratio' => 'f',
        ];

        try {
            PayloadVerify::verify($payload,$format);
            $status = true;
        } catch (PayloadVerifyException $ex) {
            $status = false;
        }
        $this->assertTrue($status);

        $this->expectException(PayloadVerifyException::class);

        $payloadBad = [
            'ratio' => '0.87345',
        ];
        PayloadVerify::verify($payloadBad,$format);
    }

    public function testPromotionBoolean() {
        $payload = [
            'value' => 0,
        ];

        $format = [
            'value' => 'biB',
        ];

        try {
            $result = $payload;
            PayloadVerify::verify($result,$format);
            $status = true;
        } catch (PayloadVerifyException $ex) {
            $status = false;
        }

        $this->assertTrue($status);
        $this->assertIsBool($result['value']);
    }

    public function testPromotionInteger() {
        $payload = [
            'value' => '33',
        ];

        $format = [
            'value' => 'siI',
        ];

        try {
            $result = $payload;
            PayloadVerify::verify($result,$format);
            $status = true;
        } catch (PayloadVerifyException $ex) {
            $status = false;
        }

        $this->assertTrue($status);
        $this->assertIsInt($result['value']);
    }

    public function testPromotionString() {
        $payload = [
            'value' => 123,
        ];

        $format = [
            'value' => 'siS',
        ];

        try {
            $result = $payload;
            PayloadVerify::verify($result,$format);
            $status = true;
        } catch (PayloadVerifyException $ex) {
            $status = false;
        }

        $this->assertTrue($status);
        $this->assertIsString($result['value']);
    }

    public function testPromotionFloat() {
        $payload = [
            'value' => '123.45',
        ];

        $format = [
            'value' => 'sfiF',
        ];

        try {
            $result = $payload;
            PayloadVerify::verify($result,$format);
            $status = true;
        } catch (PayloadVerifyException $ex) {
            $status = false;
        }

        $this->assertTrue($status);
        $this->assertIsFloat($result['value']);
    }

    public function testPromotionTrim() {
        $payload = [
            'value' => '    blah blah blah         ',
        ];

        $format = [
            'value' => 's^',
        ];

        try {
            $result = $payload;
            PayloadVerify::verify($result,$format);
            $status = true;
        } catch (PayloadVerifyException $ex) {
            $status = false;
        }

        $this->assertTrue($status);
        $this->assertEquals($result['value'],'blah blah blah');
    }

    public function testCheckNonEmpty() {
        $payload = [
            'value' => 'a',
        ];

        $format = [
            'value' => 's!',
        ];

        try {
            $result = $payload;
            PayloadVerify::verify($result,$format);
            $status = true;
        } catch (PayloadVerifyException $ex) {
            $status = false;
        }

        $this->assertTrue($status);

        $payload = [
            'value' => '',
        ];

        try {
            $result = $payload;
            PayloadVerify::verify($result,$format);
            $status = true;
        } catch (PayloadVerifyException $ex) {
            $status = false;
        }

        $this->assertFalse($status);
    }

    public function testCheckIsPositive() {
        $payload = [
            'value' => 5,
        ];

        $format = [
            'value' => 'i+',
        ];

        try {
            $result = $payload;
            PayloadVerify::verify($result,$format);
            $status = true;
        } catch (PayloadVerifyException $ex) {
            $status = false;
        }

        $this->assertTrue($status);

        $payload = [
            'value' => -5,
        ];

        try {
            $result = $payload;
            PayloadVerify::verify($result,$format);
            $status = true;
        } catch (PayloadVerifyException $ex) {
            $status = false;
        }

        $this->assertFalse($status);
    }

    public function testCheckIsNegative() {
        $payload = [
            'value' => -5,
        ];

        $format = [
            'value' => 'i-',
        ];

        try {
            $result = $payload;
            PayloadVerify::verify($result,$format);
            $status = true;
        } catch (PayloadVerifyException $ex) {
            $status = false;
        }

        $this->assertTrue($status);

        $payload = [
            'value' => 5,
        ];

        try {
            $result = $payload;
            PayloadVerify::verify($result,$format);
            $status = true;
        } catch (PayloadVerifyException $ex) {
            $status = false;
        }

        $this->assertFalse($status);
    }

    public function testCheckIsNonnegative() {
        $payload = [
            'value' => 0,
        ];

        $format = [
            'value' => 'i*',
        ];

        try {
            $result = $payload;
            PayloadVerify::verify($result,$format);
            $status = true;
        } catch (PayloadVerifyException $ex) {
            $status = false;
        }

        $this->assertTrue($status);

        $payload = [
            'value' => -5,
        ];

        try {
            $result = $payload;
            PayloadVerify::verify($result,$format);
            $status = true;
        } catch (PayloadVerifyException $ex) {
            $status = false;
        }

        $this->assertFalse($status);
    }

    public function testCheckIsNonzero() {
        $payload = [
            'value' => 1,
        ];

        $format = [
            'value' => 'i%',
        ];

        try {
            $result = $payload;
            PayloadVerify::verify($result,$format);
            $status = true;
        } catch (PayloadVerifyException $ex) {
            $status = false;
        }

        $this->assertTrue($status);

        $payload = [
            'value' => 0,
        ];

        try {
            $result = $payload;
            PayloadVerify::verify($result,$format);
            $status = true;
        } catch (PayloadVerifyException $ex) {
            $status = false;
        }

        $this->assertFalse($status);
    }

    public function testNested() {
        $payload = [
            'a' => [
                'b' => [
                    'c' => [
                        'property' => 'value',
                    ],
                ],
            ],
        ];

        $format = [
            'a' => [
                'b' => [
                    'c' => [
                        'property' => 's',
                    ],
                ],
            ],
        ];

        try {
            PayloadVerify::verify($payload,$format);
            $status = true;
        } catch (PayloadVerifyException $ex) {
            $status = false;
        }
        $this->assertTrue($status);
    }
}
