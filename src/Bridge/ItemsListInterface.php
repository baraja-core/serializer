<?php

declare(strict_types=1);

namespace Baraja\Serializer\Bridge;


interface ItemsListInterface
{
	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function getData(): array;
}
