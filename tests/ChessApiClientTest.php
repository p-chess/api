<?php

declare(strict_types=1);

namespace PChess\Api\Tests;

use PChess\Api\ChessApiClient;
use PChess\Api\ChessApiException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

final class ChessApiClientTest extends TestCase
{
    private ClientInterface&MockObject $httpClient;
    private RequestFactoryInterface&MockObject $requestFactory;
    private StreamFactoryInterface&MockObject $streamFactory;
    private ChessApiClient $client;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);

        $this->client = new ChessApiClient(
            $this->httpClient,
            $this->requestFactory,
            $this->streamFactory,
        );
    }

    #[Test]
    public function itReturnsBestMoveFromApi(): void
    {
        $fen = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
        $expectedMove = 'e2e4';
        $expectedSan = 'e4';

        $this->setupMocks($fen, [], $expectedMove, $expectedSan);

        $result = $this->client->getBestMove($fen);

        self::assertSame($expectedSan, $result);
    }

    #[Test]
    public function itSendsAllowedMovesAsSearchMoves(): void
    {
        $fen = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
        $allowedMoves = ['e2e4', 'd2d4'];
        $expectedMove = 'e2e4';
        $expectedSan = 'e4';

        $this->setupMocks($fen, $allowedMoves, $expectedMove, $expectedSan);

        $result = $this->client->getBestMove($fen, $allowedMoves);

        self::assertSame($expectedSan, $result);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function itThrowsExceptionOnHttpError(): void
    {
        $fen = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

        $stream = self::createStub(StreamInterface::class);
        $this->streamFactory->method('createStream')->willReturn($stream);

        $request = self::createStub(RequestInterface::class);
        $request->method('withHeader')->willReturnSelf();
        $request->method('withBody')->willReturnSelf();
        $this->requestFactory->method('createRequest')->willReturn($request);

        $response = self::createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);

        $this->httpClient->method('sendRequest')->willReturn($response);

        $this->expectException(ChessApiException::class);
        $this->expectExceptionMessage('API request failed with status code 500');

        $this->client->getBestMove($fen);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function itThrowsExceptionWhenMoveFieldIsMissing(): void
    {
        $fen = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

        $stream = self::createStub(StreamInterface::class);
        $this->streamFactory->method('createStream')->willReturn($stream);

        $request = self::createStub(RequestInterface::class);
        $request->method('withHeader')->willReturnSelf();
        $request->method('withBody')->willReturnSelf();
        $this->requestFactory->method('createRequest')->willReturn($request);

        $responseStream = self::createStub(StreamInterface::class);
        $responseStream->method('__toString')->willReturn('{"some": "data"}');

        $response = self::createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($responseStream);

        $this->httpClient->method('sendRequest')->willReturn($response);

        $this->expectException(ChessApiException::class);
        $this->expectExceptionMessage('Invalid API response: missing or invalid "san" field');

        $this->client->getBestMove($fen);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function itThrowsExceptionOnApiError(): void
    {
        $fen = 'invalid-fen';

        $stream = self::createStub(StreamInterface::class);
        $this->streamFactory->method('createStream')->willReturn($stream);

        $request = self::createStub(RequestInterface::class);
        $request->method('withHeader')->willReturnSelf();
        $request->method('withBody')->willReturnSelf();
        $this->requestFactory->method('createRequest')->willReturn($request);

        $responseStream = self::createStub(StreamInterface::class);
        $responseStream->method('__toString')->willReturn('{"text": "error: Invalid FEN"}');

        $response = self::createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($responseStream);

        $this->httpClient->method('sendRequest')->willReturn($response);

        $this->expectException(ChessApiException::class);
        $this->expectExceptionMessage('error: Invalid FEN');

        $this->client->getBestMove($fen);
    }

    #[Test]
    #[DataProvider('fenPositionsProvider')]
    public function itHandlesDifferentFenPositions(string $fen, string $expectedMove, string $expectedSan): void
    {
        $this->setupMocks($fen, [], $expectedMove, $expectedSan);

        $result = $this->client->getBestMove($fen);

        self::assertSame($expectedSan, $result);
    }

    /**
     * @return iterable<string, array{fen: string, expectedMove: string, expectedSan: string}>
     */
    public static function fenPositionsProvider(): iterable
    {
        yield 'starting position' => [
            'fen' => 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1',
            'expectedMove' => 'e2e4',
            'expectedSan' => 'e4',
        ];

        yield 'sicilian defense' => [
            'fen' => 'rnbqkbnr/pp1ppppp/8/2p5/4P3/8/PPPP1PPP/RNBQKBNR w KQkq c6 0 2',
            'expectedMove' => 'g1f3',
            'expectedSan' => 'Nf3',
        ];

        yield 'black to move' => [
            'fen' => 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq e3 0 1',
            'expectedMove' => 'c7c5',
            'expectedSan' => 'c5',
        ];
    }

    /**
     * @param list<string> $allowedMoves
     */
    private function setupMocks(string $fen, array $allowedMoves, string $expectedMove, string $expectedSan): void
    {
        $expectedPayload = ['fen' => $fen];
        if ([] !== $allowedMoves) {
            $expectedPayload['searchmoves'] = \implode(' ', $allowedMoves);
        }

        $stream = self::createStub(StreamInterface::class);
        $this->streamFactory
            ->expects($this->once())
            ->method('createStream')
            ->with(\json_encode($expectedPayload, JSON_THROW_ON_ERROR))
            ->willReturn($stream);

        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())
            ->method('withHeader')
            ->with('Content-Type', 'application/json')
            ->willReturnSelf();
        $request->expects($this->once())
            ->method('withBody')
            ->with($stream)
            ->willReturnSelf();

        $this->requestFactory
            ->expects($this->once())
            ->method('createRequest')
            ->with('POST', 'https://chess-api.com/v1')
            ->willReturn($request);

        $responseBody = \json_encode([
            'move' => $expectedMove,
            'san' => $expectedSan,
            'from' => \substr($expectedMove, 0, 2),
            'to' => \substr($expectedMove, 2, 2),
        ], JSON_THROW_ON_ERROR);

        $responseStream = self::createStub(StreamInterface::class);
        $responseStream->method('__toString')->willReturn($responseBody);

        $response = self::createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($responseStream);

        $this->httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);
    }
}
