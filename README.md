# Chess API Client

A PHP client for [chess-api.com](https://chess-api.com/) using PSR-18 HTTP Client.

## Requirements

- PHP 8.2+
- PSR-18 HTTP Client implementation (e.g. Symfony HttpClient, Guzzle)
- PSR-17 HTTP Factory implementation

## Installation

```bash
composer require p-chess/api
```

If you want to use Symfony HttpClient:

```bash
composer require nyholm/psr7 symfony/http-client
```

If you want to use Guzzle:

```bash
composer require guzzlehttp/guzzle
```

## Usage

```php
use PChess\Api\ChessApiClient;

// Create the client with your PSR-18/PSR-17 implementations
$client = new ChessApiClient(
    $httpClient,        // PSR-18 ClientInterface
    $requestFactory,    // PSR-17 RequestFactoryInterface
    $streamFactory,     // PSR-17 StreamFactoryInterface
);

// Get the best move for a position
$fen = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
$move = $client->getBestMove($fen);
// Returns: 'e4' (or similar)

// Restrict the engine to specific moves
$move = $client->getBestMove($fen, ['e2e4', 'd2d4', 'g1f3']);
// Returns one of the allowed moves
```

### Parameters

| Parameter       | Type     | Description                                                              |
|-----------------|----------|--------------------------------------------------------------------------|
| `$fen`          | `string` | The chess position in FEN notation (includes side to move)               |
| `$allowedMoves` | `array`  | Optional array of allowed moves in UCI format (e.g., `['e2e4', 'd2d4']`) |

### Return Value

The method returns a `string` containing the best move in UCI format (e.g., `'e2e4'`).

### Exceptions

The client throws `ChessApiException` in the following cases:

- HTTP error (non-2xx status code)
- Invalid API response (missing `move` field)
- API error (e.g., invalid FEN)

```php
use PChess\Api\ChessApiException;

try {
    $move = $client->getBestMove($fen);
} catch (ChessApiException $e) {
    // Handle the error
    echo $e->getMessage();
}
```

## Example with Symfony HttpClient

```php
use Nyholm\Psr7\Factory\Psr17Factory;
use PChess\Api\ChessApiClient;
use Symfony\Component\HttpClient\Psr18Client;

$factory = new Psr17Factory();
$httpClient = new Psr18Client();

$chessClient = new ChessApiClient($httpClient, $factory, $factory);

$fen = 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq e3 0 1';
$move = $chessClient->getBestMove($fen);

echo "Best move: $move\n";
```

## Example with Guzzle

```php
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use PChess\Api\ChessApiClient;

$httpClient = new Client();
$factory = new HttpFactory();

$chessClient = new ChessApiClient($httpClient, $factory, $factory);

$fen = 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq e3 0 1';
$move = $chessClient->getBestMove($fen);

echo "Best move: $move\n";
```

## Development

Before exdecuting one of the following, you must execute at least once

```bash
docker build -t chess-api .
```

### Running Tests

```bash
docker run --rm -v "$(pwd)":/srv/chess-api chess-api vendor/bin/phpunit --color=always
```

### Coding Standard

```bash
docker run --rm -v "$(pwd)":/srv/chess-api chess-api vendor/bin/php-cs-fixer --ansi
```

### Static Analysis

```bash
docker run --rm -v "$(pwd)":/srv/chess-api chess-api vendor/bin/phpstan --ansi
```
