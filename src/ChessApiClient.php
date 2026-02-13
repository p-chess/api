<?php

declare(strict_types=1);

namespace PChess\Api;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class ChessApiClient
{
    private const BASE_URL = 'https://chess-api.com/v1';

    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    /**
     * Get the best move from the chess API.
     *
     * @param string        $fen          The FEN position (includes side to move)
     * @param array<string> $allowedMoves Array of allowed moves in UCI format (e.g., ['e2e4', 'd2d4'])
     * @param int|null      $depth        Search depth (1-18, higher = stronger but slower)
     *
     * @return string The best move in UCI format (e.g., 'e2e4')
     *
     * @throws ChessApiException When the API returns an error or the response is invalid
     */
    public function getBestMove(string $fen, array $allowedMoves = [], ?int $depth = null): string
    {
        $payload = [
            'fen' => $fen,
        ];

        if ([] !== $allowedMoves) {
            $payload['searchmoves'] = \implode(' ', $allowedMoves);
        }

        if (null !== $depth) {
            $payload['depth'] = $depth;
        }

        $body = $this->streamFactory->createStream(\json_encode($payload, JSON_THROW_ON_ERROR));

        $request = $this->requestFactory
            ->createRequest('POST', self::BASE_URL)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);

        $response = $this->httpClient->sendRequest($request);

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new ChessApiException(
                \sprintf('API request failed with status code %d', $statusCode),
                $statusCode,
            );
        }

        $responseBody = (string) $response->getBody();
        $data = \json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);

        if (!\is_array($data)) {
            throw new ChessApiException('Invalid API response: expected JSON object');
        }

        if (isset($data['text']) && \is_string($data['text']) && \str_contains($data['text'], 'error')) {
            throw new ChessApiException($data['text']);
        }

        if (!isset($data['san']) || !\is_string($data['san'])) {
            throw new ChessApiException('Invalid API response: missing or invalid "san" field');
        }

        return $data['san'];
    }
}
