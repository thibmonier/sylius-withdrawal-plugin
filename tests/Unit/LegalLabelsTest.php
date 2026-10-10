<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusWithdrawalPlugin\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Art. 11a(1) and (3) CRD impose the wording of both buttons. Labels below are taken
 * from the Official Journal versions of Directive (EU) 2023/2673.
 */
final class LegalLabelsTest extends TestCase
{
    /** @return iterable<string, array{string, string, string}> */
    public static function officialLabels(): iterable
    {
        yield 'en' => ['en', 'withdraw from contract here', 'confirm withdrawal'];
        yield 'fr' => ['fr', 'renoncer au contrat ici', 'confirmer la rétractation'];
        yield 'de' => ['de', 'Vertrag widerrufen', 'Widerruf bestätigen'];
        yield 'es' => ['es', 'desistir del contrato aquí', 'confirmar desistimiento'];
        yield 'it' => ['it', 'recedere dal contratto qui', 'conferma recesso'];
        yield 'nl' => ['nl', 'hier de overeenkomst herroepen', 'herroeping bevestigen'];
        yield 'pl' => ['pl', 'odstąp od umowy tutaj', 'potwierdź odstąpienie od umowy'];
    }

    #[DataProvider('officialLabels')]
    public function testButtonsUseTheOfficialWording(string $locale, string $withdraw, string $confirm): void
    {
        /** @var array{tmonier_withdrawal: array{ui: array<string, string>}} $messages */
        $messages = Yaml::parseFile(sprintf('%s/../../translations/messages.%s.yaml', __DIR__, $locale));

        self::assertSame($withdraw, $messages['tmonier_withdrawal']['ui']['withdraw_from_contract_here']);
        self::assertSame($confirm, $messages['tmonier_withdrawal']['ui']['confirm_withdrawal']);
    }

    #[DataProvider('officialLabels')]
    public function testEveryLocaleDefinesTheSameKeys(string $locale): void
    {
        $reference = $this->flattenKeys(Yaml::parseFile(__DIR__ . '/../../translations/messages.en.yaml'));
        $keys = $this->flattenKeys(Yaml::parseFile(sprintf('%s/../../translations/messages.%s.yaml', __DIR__, $locale)));

        self::assertSame([], array_values(array_diff($reference, $keys)), 'Missing keys');
        self::assertSame([], array_values(array_diff($keys, $reference)), 'Unknown keys');
    }

    /** @return list<string> */
    private function flattenKeys(mixed $data, string $prefix = ''): array
    {
        if (!is_array($data)) {
            return [rtrim($prefix, '.')];
        }

        $keys = [];
        foreach ($data as $key => $value) {
            $keys = [...$keys, ...$this->flattenKeys($value, $prefix . $key . '.')];
        }

        return $keys;
    }
}
