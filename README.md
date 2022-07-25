PHP Serializer
==============

A simple serializer that creates a simple scalar array based on any PHP object or other data structure.

The serializer automatically handles backward and forward compatibility. Automatically handles security.

The output is ready to be sent via REST API.

How to use
----------

```php
class DTO {
	__construct(
		public string $name,
	) {
	}
}

$serializer = Serializer::get();
var_dump($serializer->serialize(
	new DTO(name: 'Jan'),
));
```
