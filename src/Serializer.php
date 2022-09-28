<?php

declare(strict_types=1);

namespace Baraja\Serializer;


use Baraja\EcommerceStandard\DTO\PriceInterface;
use Baraja\Localization\Translation;
use Baraja\Serializer\Bridge\ItemsListInterface;
use Baraja\Serializer\Bridge\StatusCountInterface;
use Nette\Utils\Paginator;
use Tracy\Debugger;
use Tracy\ILogger;

/**
 * Serialize any PHP typed object (DTO) or scalar type to simple array.
 */
final class Serializer
{
	private const HiddenKeyLabel = '*****';

	private static ?self $singleton = null;


	public function __construct(
		private SerializerConvention $convention,
	) {
	}


	public static function get(): self
	{
		if (self::$singleton === null) {
			self::$singleton = new self(new SerializerConvention);
		}

		return self::$singleton;
	}


	/**
	 * @param object|mixed[] $haystack
	 * @return mixed[]
	 */
	public function serialize(object|array $haystack): array
	{
		if (is_array($haystack)) {
			return $this->processArray($haystack, 0);
		}
		if (is_iterable($haystack)) {
			$return = [];
			foreach ($haystack as $key => $value) {
				$return[$key] = $this->hideKey((string) $key, $value)
					? self::HiddenKeyLabel
					: $this->process($value, 0);
			}

			return $return;
		}

		return $this->processObject($haystack, 0);
	}


	/**
	 * @param array<string, bool> $trackedInstanceHashes (key => true)
	 * @return float|int|bool|array<int|string, mixed>|string|null
	 */
	private function process(
		mixed $haystack,
		int $level,
		array $trackedInstanceHashes = [],
	): float|null|int|bool|array|string {
		if ($level >= 32) {
			throw new \LogicException('Structure is too deep.');
		}
		if (is_scalar($haystack) || $haystack === null) {
			return $haystack;
		}
		if (is_array($haystack)) {
			return $this->processArray($haystack, $level, $trackedInstanceHashes);
		}
		if (is_object($haystack)) {
			if (class_exists(Translation::class) && $haystack instanceof Translation) {
				return (string) $haystack;
			}
			if ($haystack instanceof \DateTimeInterface) {
				return $haystack->format($this->convention->getDateTimeFormat());
			}
			if (class_exists(Paginator::class) && $haystack instanceof Paginator) {
				return $this->processPaginator($haystack);
			}
			if ($haystack instanceof StatusCountInterface) {
				return $this->processStatusCount($haystack);
			}
			if ($haystack instanceof ItemsListInterface) {
				return $this->process($haystack->getData(), $level, $trackedInstanceHashes);
			}
			if ($haystack instanceof \UnitEnum) {
				return $this->processEnum($haystack);
			}
			if (interface_exists(PriceInterface::class) && $haystack instanceof PriceInterface) {
				return $this->processPrice($haystack);
			}
			if ($this->convention->isRewriteTooStringMethod() && \method_exists($haystack, '__toString') === true) {
				return (string) $haystack;
			}

			return $this->processObject($haystack, $level, $trackedInstanceHashes);
		}

		throw new \InvalidArgumentException(
			sprintf(
				'Value type "%s" can not be serialized.',
				get_debug_type($haystack),
			),
		);
	}


	/**
	 * @param array<string, bool> $trackedInstanceHashes (key => true)
	 * @return array<string, mixed>
	 */
	private function processObject(object $haystack, int $level, array $trackedInstanceHashes = []): array
	{
		$values = [];
		if (!$haystack instanceof \stdClass && class_exists(get_class($haystack))) {
			$ref = new \ReflectionClass($haystack);
			foreach ($ref->getProperties() as $property) {
				$property->setAccessible(true);
				$key = $property->getName();
				if (($key[0] ?? '') === '_') { // (security) ignore internal properties
					continue;
				}
				$values[$property->getName()] = $property->getValue($haystack);
			}
		} else {
			$values = get_object_vars($haystack);
		}

		$return = [];
		foreach ($values as $key => $value) {
			if ($value === null && $this->convention->isRewriteNullToUndefined()) {
				continue;
			}
			if (is_object($value) === true) {
				$objectHash = spl_object_hash($value);
				if (isset($trackedInstanceHashes[$objectHash]) === true) {
					throw new \InvalidArgumentException(
						'Attention: Recursion has been stopped! BaseResponse detected an infinite recursion that was automatically stopped.'
						. "\n\n" . 'To resolve this issue: Never pass entire recursive entities to the API. If you can, pass the processed field without recursion.',
					);
				}
				$trackedInstanceHashes[$objectHash] = true;
			}
			$return[$key] = $this->process($value, $level, $trackedInstanceHashes);
		}

		return $return;
	}


	/**
	 * @param mixed[] $haystack
	 * @param array<string, bool> $trackedInstanceHashes (key => true)
	 * @return mixed[]
	 */
	private function processArray(array $haystack, int $level, array $trackedInstanceHashes = []): array
	{
		$return = [];
		foreach ($haystack as $key => $value) {
			if ($value === null && $this->convention->isRewriteNullToUndefined()) {
				continue;
			}
			if ($value instanceof ItemsListInterface && $key !== 'items') {
				throw new \InvalidArgumentException(
					sprintf('Convention error: Item list must be in key "items", but "%s" given.', $key),
				);
			}
			if ($value instanceof Paginator && $key !== 'paginator') {
				throw new \InvalidArgumentException(
					sprintf('Convention error: Paginator must be in key "paginator", but "%s" given.', $key),
				);
			}
			$return[$key] = $this->hideKey((string) $key, $value)
				? self::HiddenKeyLabel
				: $this->process($value, $level, $trackedInstanceHashes);
		}

		return $return;
	}


	/**
	 * @return array{
	 *     page: int,
	 *     pageCount: int,
	 *     itemCount: int,
	 *     itemsPerPage: int,
	 *     firstPage: int,
	 *     lastPage: int,
	 *     isFirstPage: bool,
	 *     isLastPage: bool
	 *  }
	 */
	private function processPaginator(Paginator $haystack): array
	{
		return [
			'page' => $haystack->getPage(),
			'pageCount' => (int) $haystack->getPageCount(),
			'itemCount' => (int) $haystack->getItemCount(),
			'itemsPerPage' => $haystack->getItemsPerPage(),
			'firstPage' => $haystack->getFirstPage(),
			'lastPage' => (int) $haystack->getLastPage(),
			'isFirstPage' => $haystack->isFirst(),
			'isLastPage' => $haystack->isLast(),
		];
	}


	/**
	 * @return array{key: string, label: string, count: int}
	 */
	private function processStatusCount(StatusCountInterface $haystack): array
	{
		return [
			'key' => $haystack->getKey(),
			'label' => $haystack->getLabel(),
			'count' => $haystack->getCount(),
		];
	}


	private function processEnum(\UnitEnum $enum): string|int
	{
		return $enum->value ?? $enum->name;
	}


	/**
	 * @return array{value: string, currency: string, html: string, isFree: bool}
	 */
	private function processPrice(PriceInterface $price): array
	{
		return [
			'value' => $price->getValue(),
			'currency' => $price->getCurrency()->getSymbol(),
			'html' => $price->render(true),
			'isFree' => $price->isFree(),
		];
	}


	private function hideKey(string $key, mixed $value): bool
	{
		static $hide;

		if ($hide === null) {
			$hide = [];
			foreach ($this->convention->getKeysToHide() as $hideKey) {
				$hide[$hideKey] = true;
			}
		}
		if (isset($hide[$key]) && (is_string($value) || $value instanceof \Stringable)) {
			if (preg_match('/^\$2[ayb]\$.{56}$/', (string) $value) === 1) { // Allow BCrypt hash only.
				return false;
			}
			if (\class_exists(Debugger::class) === true) {
				Debugger::log(
					new \RuntimeException(
						sprintf('Security warning: User password may have been compromised! Key "%s" given.', $key)
						. "\n" . 'The Baraja API prevented passwords being passed through the API in a readable form.',
					),
					ILogger::CRITICAL,
				);
			}

			return true;
		}

		return false;
	}
}
