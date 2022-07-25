<?php

declare(strict_types=1);

namespace Baraja\Serializer\Bridge;


interface StatusCountInterface
{
	public function getKey(): string;

	public function getLabel(): string;

	public function getCount(): int;
}
