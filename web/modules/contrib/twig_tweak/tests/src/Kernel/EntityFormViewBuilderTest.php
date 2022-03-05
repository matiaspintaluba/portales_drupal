<?php

namespace Drupal\Tests\twig_tweak\Kernel;

use Drupal\Core\Cache\Cache;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * A test for EntityFormViewBuilder.
 *
 * @group twig_tweak
 */
final class EntityFormViewBuilderTest extends AbstractTestCase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'twig_tweak',
    'twig_tweak_test',
    'user',
    'system',
    'node',
    'field',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->installEntitySchema('node');
    NodeType::create(['type' => 'article'])->save();
    $this->setUpCurrentUser(
      ['name' => 'User 1'],
      ['edit any article content', 'access content'],
    );
  }

  /**
   * Test callback.
   */
  public function testEntityFormViewBuilder(): void {

    $view_builder = $this->container->get('twig_tweak.entity_form_view_builder');

    $values = [
      'type' => 'article',
      'title' => 'Public node',
    ];
    $public_node = Node::create($values);
    $public_node->save();

    $values = [
      'type' => 'article',
      'title' => 'Private node',
    ];
    $private_node = Node::create($values);
    $private_node->save();

    // -- Default mode.
    $build = $view_builder->build($public_node);

    self::assertArrayHasKey('#form_id', $build);
    $expected_cache = [
      'contexts' => [
        'user',
        'user.permissions',
        'user.roles:authenticated',
      ],
      'tags' => [
        'config:core.entity_form_display.node.article.default',
        'node:1',
        'tag_from_twig_tweak_test_node_access',
      ],
      'max-age' => 50,
    ];
    self::assertCache($expected_cache, $build['#cache']);
    self::assertStringContainsString('<form class="node-article-form node-form" ', $this->renderPlain($build));

    // -- Private node with access check.
    $build = $view_builder->build($private_node);

    self::assertArrayNotHasKey('#form_id', $build);
    $expected_cache = [
      'contexts' => [
        'user',
        'user.permissions',
      ],
      'tags' => [
        'node:2',
        'tag_from_twig_tweak_test_node_access',
      ],
      'max-age' => 50,
    ];
    self::assertCache($expected_cache, $build['#cache']);
    self::assertSame('', $this->renderPlain($build));

    // -- Private node without access check.
    $build = $view_builder->build($private_node, 'default', FALSE);

    self::assertArrayHasKey('#form_id', $build);
    $expected_cache = [
      'contexts' => [
        'user.roles:authenticated',
      ],
      'tags' => [
        'config:core.entity_form_display.node.article.default',
        'node:2',
      ],
      'max-age' => Cache::PERMANENT,
    ];
    self::assertCache($expected_cache, $build['#cache']);
    self::assertStringContainsString('<form class="node-article-form node-form" ', $this->renderPlain($build));
  }

  /**
   * Renders a render array.
   */
  private function renderPlain(array $build): string {
    return $this->container->get('renderer')->renderPlain($build);
  }

}
