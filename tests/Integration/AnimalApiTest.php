<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\User\Role;

final class AnimalApiTest extends AppTestCase
{
    private string $operatorToken;
    private string $clientToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operatorToken = $this->tokenFor($this->createUser('operator@lidafacil.local', Role::OPERATOR));
        $this->clientToken = $this->tokenFor($this->createUser('client@lidafacil.local', Role::CLIENT));
    }

    /**
     * @return array<string,string>
     */
    private function validAnimal(string $tag = 'BOV-001'): array
    {
        return [
            'tag' => $tag,
            'species' => 'bovino',
            'sex' => 'femea',
            'weight_kg' => '450.5',
            'birth_date' => '2024-01-10',
            'notes' => 'novilha em bom estado',
        ];
    }

    public function testClientCannotAccessAnimals(): void
    {
        self::assertSame(403, $this->request('GET', '/api/v1/animals', null, $this->clientToken)->getStatusCode());
    }

    public function testOperatorCanListAnimals(): void
    {
        $response = $this->request('GET', '/api/v1/animals', null, $this->operatorToken);

        self::assertSame(200, $response->getStatusCode());
        self::assertIsArray($this->json($response)['data'] ?? null);
    }

    public function testOperatorCanCreateAnimal(): void
    {
        $response = $this->request('POST', '/api/v1/animals', $this->validAnimal(), $this->operatorToken);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('BOV-001', $this->json($response)['data']['tag'] ?? null);
    }

    public function testClientCannotCreateAnimal(): void
    {
        $response = $this->request('POST', '/api/v1/animals', $this->validAnimal(), $this->clientToken);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testInvalidAnimalIsUnprocessable(): void
    {
        $body = $this->validAnimal();
        $body['species'] = 'cavalo';

        $response = $this->request('POST', '/api/v1/animals', $body, $this->operatorToken);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testDuplicateTagIsConflict(): void
    {
        $this->request('POST', '/api/v1/animals', $this->validAnimal(), $this->operatorToken);
        $again = $this->request('POST', '/api/v1/animals', $this->validAnimal(), $this->operatorToken);

        self::assertSame(409, $again->getStatusCode());
    }

    public function testFullCrudFlow(): void
    {
        $created = $this->json($this->request('POST', '/api/v1/animals', $this->validAnimal('OVI-050'), $this->operatorToken));
        $id = (int) ($created['data']['id'] ?? 0);
        self::assertGreaterThan(0, $id);

        $show = $this->request('GET', '/api/v1/animals/' . $id, null, $this->operatorToken);
        self::assertSame(200, $show->getStatusCode());

        $update = $this->validAnimal('OVI-050');
        $update['weight_kg'] = '480';
        $updated = $this->request('PUT', '/api/v1/animals/' . $id, $update, $this->operatorToken);
        self::assertSame(200, $updated->getStatusCode());

        $delete = $this->request('DELETE', '/api/v1/animals/' . $id, null, $this->operatorToken);
        self::assertSame(204, $delete->getStatusCode());

        self::assertSame(404, $this->request('GET', '/api/v1/animals/' . $id, null, $this->operatorToken)->getStatusCode());
    }

    public function testVaccinationSubresource(): void
    {
        $animalId = (int) $this->json(
            $this->request('POST', '/api/v1/animals', $this->validAnimal('SUI-010'), $this->operatorToken),
        )['data']['id'];

        $vaccination = $this->request('POST', '/api/v1/animals/' . $animalId . '/vaccinations', [
            'vaccine_name' => 'Aftosa',
            'dose' => '1a dose',
            'applied_at' => '2025-05-10',
        ], $this->operatorToken);
        self::assertSame(201, $vaccination->getStatusCode());
        $vaccinationId = (int) $this->json($vaccination)['data']['id'];

        $list = $this->request('GET', '/api/v1/animals/' . $animalId . '/vaccinations', null, $this->operatorToken);
        self::assertSame(200, $list->getStatusCode());

        // apagar apontando para outro animal não pode remover o registro (verificação de pertencimento)
        $wrongAnimal = $this->request('DELETE', '/api/v1/animals/999/vaccinations/' . $vaccinationId, null, $this->operatorToken);
        self::assertSame(404, $wrongAnimal->getStatusCode());

        $delete = $this->request('DELETE', '/api/v1/animals/' . $animalId . '/vaccinations/' . $vaccinationId, null, $this->operatorToken);
        self::assertSame(204, $delete->getStatusCode());
    }
}
