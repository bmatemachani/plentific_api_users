<?php

namespace Drupal\Tests\custom_api_block\Unit\Plugin\Block;

use Drupal\plentific_api_users\Plugin\Block\UsersListingBlock;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * Unit tests for the CustomApiBlock plugin.
 *
 * @group plentific_api_users
 */
class UsersListingBlockTest extends UnitTestCase {

  /**
   * The block plugin.
   *
   * @var \Drupal\plentific_api_users\Plugin\Block\UsersListingBlock
   */
  protected $block;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock the HTTP client.
    $mock = new MockHandler([
      new Response(200, [], json_encode(['key' => 'value'])),
    ]);
    $handlerStack = HandlerStack::create($mock);
    $client = new Client(['handler' => $handlerStack]);

    // Create a container with the mocked HTTP client.
    $container = new ContainerBuilder();
    $container->set('http_client', $client);
    \Drupal::setContainer($container);

    // Instantiate the block.
    $this->block = new UsersListingBlock([], 'users_listing_block', [], $client);
  }

  /**
   * Tests the build() method of the block.
   */
  public function testBuild() {
    // Set the API URL in the block configuration.
    $this->block->setConfiguration(['api_url' => 'https://reqres.in/api/users']);

    // Build the block.
    $build = $this->block->build();

    // Assert that the block returns the expected data.
    $this->assertArrayHasKey('#markup', $build);
    $this->assertStringContainsString('Data from API:', $build['#markup']);
    $this->assertStringContainsString('value', $build['#markup']);
  }

  /**
   * Tests the build() method when the API URL is not configured.
   */
  public function testBuildWithoutApiUrl() {
    // Build the block without setting the API URL.
    $build = $this->block->build();

    // Assert that the block returns an error message.
    $this->assertArrayHasKey('#markup', $build);
    $this->assertStringContainsString('API URL is not configured.', $build['#markup']);
  }

  /**
   * Tests the build() method when the API request fails.
   */
  public function testBuildWithApiError() {
    // Mock an API error.
    $mock = new MockHandler([
      new Response(500, [], 'Internal Server Error'),
    ]);
    $handlerStack = HandlerStack::create($mock);
    $client = new Client(['handler' => $handlerStack]);

    // Instantiate the block with the mocked client.
    $block = new UsersListingBlock([], 'users_listing_block', [], $client);
    $block->setConfiguration(['api_url' => 'https://reqres.in/api/users']);

    // Build the block.
    $build = $block->build();

    // Assert that the block returns an error message.
    $this->assertArrayHasKey('#markup', $build);
    $this->assertStringContainsString('Failed to fetch data from API:', $build['#markup']);
  }
}
