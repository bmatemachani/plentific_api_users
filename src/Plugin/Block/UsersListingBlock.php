<?php

namespace Drupal\plentific_api_users\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Pager\PagerParametersInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Users Listing' Block.
 *
 * @Block(
 *   id = "users_listing_block",
 *   admin_label = @Translation("Plentif Users Block"),
 *   category = @Translation("Custom"),
 * )
 */
class UsersListingBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * HTTP client.
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Pager manager.
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * Pager parameters.
   * @var \Drupal\Core\Pager\PagerParametersInterface
   */
  protected $pagerParameters;

  public function __construct(array $configuration, $plugin_id, $plugin_definition,PagerManagerInterface $pager_manager,
                              PagerParametersInterface $pager_parameters, ClientInterface $http_client) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->pagerManager = $pager_manager;
    $this->pagerParameters = $pager_parameters;
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('pager.manager'),
      $container->get('pager.parameters'),
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $config = $this->getConfiguration();
    $api_url = $config['api_url'] ?? '';
    $items_per_page = $config['items_per_page'] ?? 4;
    $email_label = $config['email_label'] ?? 'Email';
    $forename_label = $config['forename_label'] ?? 'Forename';
    $surname_label = $config['surname_label'] ?? 'Surname';

    if (empty($api_url)) {
      return [
        '#markup' => $this->t('API URL is not configured.'),
      ];
    }

    try {
      $res  = $this->httpClient->get($api_url);
      $data = $res->getBody()->getContents();
      $obj  = json_decode($data, TRUE);
      $api_users =  $obj['data'];

      /*
        Putting this here for future filtering users. This can be extended to a function to pass
        a list of values that can then be filtered out.
      */
      $filter_by_name = '';
      $filtered_api_users = array_filter($api_users, function($item) use ( $filter_by_name) {
        return $item['first_name'] !== $filter_by_name;
      });

      $current_page = $this->pagerParameters->findPage();
      $total_users = count($filtered_api_users);
      $this->pagerManager->createPager($total_users, $items_per_page);

      // Split the array into chunks for pagination.
      $chunks = array_chunk($filtered_api_users, $items_per_page, TRUE);
      $current_chunk = $chunks[$current_page] ?? [];

      // Build the table rows.
      $rows = [];
      foreach ($current_chunk as $user) {
        $rows[] = [
          'data' => [
            $user['email'],
            $user['first_name'],
            $user['last_name'],
          ],
        ];
      }

      // Define the table header.
      $header = [
        'email' => $this->t($email_label),
        'first_name' => $this->t($forename_label),
        'last_name' => $this->t($surname_label),
      ];

      // Build the render array.
      $build = [];
      $build['table'] = [
        '#theme'  => 'table',
        '#header' => $header,
        '#rows'   => $rows,
      ];

      // Add the pager.
      $build['pager'] = [
        '#type' => 'pager',
      ];
      return $build;

    } catch (\Exception $e) {
      return [
        '#markup' => $this->t('Failed to fetch data from API: @error', ['@error' => $e->getMessage()]),
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    $form['api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API URL'),
      '#default_value' => $config['api_url'] ?? '',
      '#required' => TRUE,
    ];

    $form['items_per_page'] = [
      '#type' => 'number',
      '#title' => $this->t('Items Per Page'),
      '#default_value' => $config['items_per_page'] ?? 4,
      '#required' => TRUE,
    ];

    $form['email_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email field label'),
      '#default_value' => $config['email_label'] ?? 'Email',
      '#required' => FALSE,
    ];

    $form['forename_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Forename field label'),
      '#default_value' => $config['forename_label'] ?? 'Forename',
      '#required' => FALSE,
    ];
    $form['surname_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Surname field label'),
      '#default_value' => $config['surname_label'] ?? 'Surname',
      '#required' => FALSE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $this->configuration['api_url']         = $form_state->getValue('api_url');
    $this->configuration['items_per_page']  = $form_state->getValue('items_per_page');
    $this->configuration['email_label']     = $form_state->getValue('email_label');
    $this->configuration['forename_label']  = $form_state->getValue('forename_label');
    $this->configuration['surname_label']   = $form_state->getValue('surname_label');
  }
}


