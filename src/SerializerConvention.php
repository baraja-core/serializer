<?php

declare(strict_types=1);

namespace Baraja\Serializer;


class SerializerConvention
{
	private string $dateTimeFormat = 'Y-m-d H:i:s';

	private bool $rewriteTooStringMethod = true;

	/**
	 * If the property value is "null", it is automatically removed.
	 * This option optimizes the size of the transferred data.
	 */
	private bool $rewriteNullToUndefined = false;

	/** @var array<int, string> */
	private array $keysToHide = ['password', 'passwd', 'pass', 'pwd', 'creditcard', 'credit card', 'cc', 'pin'];


	public function getDateTimeFormat(): string
	{
		return $this->dateTimeFormat;
	}


	public function isRewriteTooStringMethod(): bool
	{
		return $this->rewriteTooStringMethod;
	}


	public function isRewriteNullToUndefined(): bool
	{
		return $this->rewriteNullToUndefined;
	}


	/**
	 * @return array<int, string>
	 */
	public function getKeysToHide(): array
	{
		return $this->keysToHide;
	}
}
