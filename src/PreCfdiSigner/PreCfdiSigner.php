<?php

declare(strict_types=1);

namespace Dufrei\ApiJsonCfdiBridge\PreCfdiSigner;

use CfdiUtils\CadenaOrigen\CfdiDefaultLocations;
use CfdiUtils\CadenaOrigen\XsltBuilderInterface;
use CfdiUtils\XmlResolver\XmlResolver;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use LogicException;
use Throwable;

class PreCfdiSigner
{
    private DOMDocument $document;

    private DOMXPath $xpath;

    private XmlResolver $xmlResolver;

    private XsltBuilderInterface $xsltBuilder;

    public function __construct(DOMDocument $document, XmlResolver $xmlResolver, XsltBuilderInterface $xsltBuilder)
    {
        $xpath = new DOMXPath($document, false);
        //$xpath->registerNamespace('cfdi', 'http://www.sat.gob.mx/cfd/3');
        $xpath->registerNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');

        $this->document = $document;
        $this->xpath = $xpath;
        $this->xmlResolver = $xmlResolver;
        $this->xsltBuilder = $xsltBuilder;
    }

    public function putCertificateValue(string $certificate): void
    {
        $this->findFirstElement('/cfdi:Comprobante')?->setAttribute('Certificado', $certificate);
    }

    public function putCertificateNumber(string $certificateNumber): void
    {
        $this->findFirstElement('/cfdi:Comprobante')?->setAttribute('NoCertificado', $certificateNumber);
    }

    public function putSignatureValue(string $signature): void
    {
        $this->findFirstElement('/cfdi:Comprobante')?->setAttribute('Sello', $signature);
    }

    /** @throws UnableToSignXmlException */
    public function putIssuerRfc(string $rfc): void
    {
        $issuer = $this->findFirstElement('/cfdi:Comprobante/cfdi:Emisor');
        if (null === $issuer) {
            return;
        }

        $currentRfc = $issuer->getAttribute('Rfc');
        if ('' !== $currentRfc && $currentRfc !== $rfc) {
            throw new UnableToSignXmlException("The issuer RFC on data $currentRfc is different from CSD $rfc");
        }

        $issuer->setAttribute('Rfc', $rfc);
    }

    /** @throws UnableToSignXmlException */
    public function buildSourceString(): string
    {
        try {
            //$xsltLocation = $this->xmlResolver->resolve(CfdiDefaultLocations::XSLT_33);
            $xsltLocation = $this->xmlResolver->resolve(CfdiDefaultLocations::XSLT_40);
            return $this->xsltBuilder->build($this->document->saveXML() ?: '', $xsltLocation);
        } catch (Throwable $exception) {
            throw new UnableToSignXmlException('Unable to build source string', $exception);
        }
    }

    private function findFirstElement(string $elementPath): ?DOMElement
    {
        foreach ($this->query($elementPath) as $node) {
            if ($node instanceof DOMElement) {
                return $node;
            }
        }

        return null;
    }

    /**
     * @param string $xpathQuery
     * @return DOMNodeList<DOMNode>
     */
    private function query(string $xpathQuery): DOMNodeList
    {
        $nodeList = $this->xpath->query($xpathQuery);
        if (false === $nodeList) {
            throw new LogicException("Invalid XPath query: '$xpathQuery'"); // @codeCoverageIgnore
        }
        return $nodeList;
    }
}
