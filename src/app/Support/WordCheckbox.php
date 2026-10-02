<?php

namespace App\Support;

use DOMElement;
use DOMXPath;

class WordCheckbox
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const W14 = 'http://schemas.microsoft.com/office/word/2010/wordml';

    public static function setModern(DOMXPath $xpath, DOMElement $checkbox, bool $selected): void
    {
        foreach (['checked' => $selected ? '1' : '0', 'checkedState' => '2612', 'uncheckedState' => '2610'] as $name => $value) {
            $state = $xpath->query('./w14:'.$name, $checkbox)->item(0);
            if (! $state instanceof DOMElement) {
                $state = $checkbox->ownerDocument->createElementNS(self::W14, 'w14:'.$name);
                $checkbox->appendChild($state);
            }
            $state->setAttributeNS(self::W14, 'w14:val', $value);
            if ($name !== 'checked') {
                $state->setAttributeNS(self::W14, 'w14:font', 'MS Gothic');
            }
        }
        $displayText = $xpath->query('ancestor::w:sdt[1]/w:sdtContent//w:t', $checkbox)->item(0);
        if ($displayText instanceof DOMElement) {
            $displayText->nodeValue = $selected ? '☒' : '☐';
        }
    }

    public static function setLegacy(DOMXPath $xpath, DOMElement $checkbox, bool $selected): void
    {
        foreach (['default', 'checked'] as $name) {
            $state = $xpath->query('./w:'.$name, $checkbox)->item(0);
            if (! $state instanceof DOMElement) {
                $state = $checkbox->ownerDocument->createElementNS(self::W, 'w:'.$name);
                $checkbox->appendChild($state);
            }
            $state->setAttributeNS(self::W, 'w:val', $selected ? '1' : '0');
        }
    }
}
