<?php

declare(strict_types=1);

namespace App\Tests\Controllers;

use App\Controllers\BuildCfdiFromJsonController;
use App\Tests\TestCase;
use Dufrei\ApiJsonCfdiBridge\Factory;
use Dufrei\ApiJsonCfdiBridge\StampService\StampErrors;
use Dufrei\ApiJsonCfdiBridge\StampService\StampException;
use Dufrei\ApiJsonCfdiBridge\Tests\Fakes\FakeFactory;
use Dufrei\ApiJsonCfdiBridge\Tests\Fakes\FakeStampService;
use Dufrei\ApiJsonCfdiBridge\Values\Cfdi;
use Dufrei\ApiJsonCfdiBridge\Values\Uuid;
use Dufrei\ApiJsonCfdiBridge\Values\XmlContent;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * @see BuildCfdiFromJsonController
 */
final class BuildCfdiFromJsonControllerTest extends TestCase
{
    private function createValidFormRequestWithJson(string $json): Request
    {
        return $this->createFormRequest('POST', '/build-cfdi-from-json', $this->getTestingToken(), [
            'json' => $json,
            'certificate' => $this->fileContents('fake-csd/EKU9003173C9.cer'),
            'privatekey' => $this->fileContents('fake-csd/EKU9003173C9.key'),
            'passphrase' => trim($this->fileContents('fake-csd/EKU9003173C9-password.txt')),
        ]);
    }

    private function setUpContainerWithFakeStampService(Cfdi|StampException|null $result = null): void
    {
        $factory = FakeFactory::create();
        $stampService = new FakeStampService(array_filter([$result]));
        $factory->setStampService($stampService);
        $this->getContainer()->add(Factory::class, $factory);
    }

    public function testBuildCfdiFromJsonUsingFakeStampService(): void
    {
        $cfdi = new Cfdi(
            new Uuid('CEE4BE01-ADFA-4DEB-8421-ADD60F0BEDAC'),
            new XmlContent($this->fileContents('stamped.xml')),
        );
        $this->setUpContainerWithFakeStampService($cfdi);
        $request = $this->createValidFormRequestWithJson($this->fileContents('invoice.json'));
        $response = $this->getApp()->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $responseData = json_decode((string) $response->getBody());
        $this->assertStringEqualsFile($this->filePath('converted.xml'), $responseData->converted);
        $this->assertStringEqualsFile($this->filePath('sourcestring.txt'), $responseData->sourcestring);
        $this->assertStringEqualsFile($this->filePath('signed.xml'), $responseData->precfdi);
        $this->assertEquals($cfdi->getUuid(), $responseData->uuid);
        $this->assertEquals($cfdi->getXml(), $responseData->xml);
    }

    public function testControllerAccessUsesToken(): void
    {
        $request = $this->createFormRequest('POST', '/build-cfdi-from-json');
        $response = $this->getApp()->handle($request);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testValidatesNoInputs(): void
    {
        $request = $this->createFormRequest('POST', '/build-cfdi-from-json', $this->getTestingToken());
        $response = $this->getApp()->handle($request);

        $this->assertSame(400, $response->getStatusCode());
        $responseData = json_decode((string) $response->getBody());
        $this->assertSame('Invalid input', $responseData->message);
        $this->assertSame('The json input is required', $responseData->errors->json);
        $this->assertSame('The certificate content is required', $responseData->errors->certificate);
        $this->assertSame('The private key content is required', $responseData->errors->privatekey);
        $this->assertSame('The private key passphrase must be present', $responseData->errors->passphrase);
    }

    public function testValidatesJsonInput(): void
    {
        $this->setUpContainerWithFakeStampService();
        $request = $this->createValidFormRequestWithJson('invalid json');
        $response = $this->getApp()->handle($request);

        $this->assertSame(400, $response->getStatusCode());
        $responseData = json_decode((string) $response->getBody());
        $this->assertSame('Invalid input', $responseData->message);
        $this->assertSame('The json input must be a valid JSON string', $responseData->errors->json);
    }

    public function testControllerValidatesCredential(): void
    {
        $request = $this->createFormRequest('POST', '/build-cfdi-from-json', $this->getTestingToken(), [
            'json' => '{}',
            'certificate' => 'CER',
            'privatekey' => 'KEY',
            'passphrase' => '',
        ]);
        $response = $this->getApp()->handle($request);

        $this->assertSame(400, $response->getStatusCode());
        $responseData = json_decode((string) $response->getBody());
        $this->assertSame('Invalid input', $responseData->message);
        $this->assertSame(
            'Unable to create a credential using certificate, private key and passphrase',
            $responseData->errors[0]
        );
    }

    public function testUnableToSignXml(): void
    {
        $this->setUpContainerWithFakeStampService();
        // replace issuer rfc to produce error
        $json = str_replace('EKU9003173C9', 'AAA010101AAA', $this->fileContents('invoice.json'));
        $request = $this->createValidFormRequestWithJson($json);
        $response = $this->getApp()->handle($request);

        $this->assertSame(400, $response->getStatusCode());
        $responseData = json_decode((string) $response->getBody());
        $this->assertSame('Invalid input', $responseData->message);
        $this->assertStringContainsString('EKU9003173C9', $responseData->errors[0]);
    }

    public function testUnableToStampCfdi(): void
    {
        $this->setUpContainerWithFakeStampService(
            new StampException('Fake message', new StampErrors())
        );
        $request = $this->createValidFormRequestWithJson($this->fileContents('invoice.json'));
        $response = $this->getApp()->handle($request);

        $this->assertSame(400, $response->getStatusCode());
        $responseData = json_decode((string) $response->getBody());
        $this->assertSame('Invalid input', $responseData->message);
        $this->assertStringContainsString('Fake message', $responseData->errors[0]);
    }
}