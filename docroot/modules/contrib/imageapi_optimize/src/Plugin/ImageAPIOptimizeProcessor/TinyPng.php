<?php

namespace Drupal\imageapi_optimize\Plugin\ImageAPIOptimizeProcessor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\imageapi_optimize\ConfigurableImageAPIOptimizeProcessorBase;
use Drupal\imageapi_optimize\ImageAPIOptimizeProcessorBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Tinify\Tinify;

/**
 * Uses the TinyPNG webservice to optimize an image.
 *
 * @ImageAPIOptimizeProcessor(
 *   id = "tinypng",
 *   label = @Translation("TinyPNG"),
 *   description = @Translation("Uses the TinyPNG service to optimize jpeg and png images.")
 * )
 */
class TinyPng extends ConfigurableImageAPIOptimizeProcessorBase {

  /**
   * The Tinify PHP library to communicate with TinyPNG service.
   *
   * @var $tinify Tinify
   */
  protected $tinify;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger, ImageFactory $image_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger, $image_factory);

    //$this->tinify = $tinify;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('imageapi_optimize'),
      $container->get('image.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function applyToImage($image_uri) {
    $apiKey = $this->configuration['api_key'];
    try {
      // TODO: Handle as protected tinify service.
      \Tinify\setKey($apiKey);
      $sourceImage = file_get_contents($image_uri);
      if ($optimizedImage = \Tinify\fromBuffer($sourceImage)->toBuffer()) {
        if (file_unmanaged_save_data($optimizedImage, $image_uri, FILE_EXISTS_REPLACE)) {
          return TRUE;
        }
      }
    } catch(\Tinify\AccountException $e) {
      $this->logger->error('TinyPNG - AccountException: Failed to download optimize image using TinyPNG due to "%error".', array('%error' => $e->getMessage()));
      // Verify your API key and account limit.
    } catch(\Tinify\ClientException $e) {
      $this->logger->error('TinyPNG - ClientException: Failed to download optimize image using TinyPNG due to "%error".', array('%error' => $e->getMessage()));
      // Check your source image and request options.
    } catch(\Tinify\ServerException $e) {
      $this->logger->error('TinyPNG - ConnectionException: Failed to download optimize image using TinyPNG due to "%error".', array('%error' => $e->getMessage()));
      // Temporary issue with the Tinify API.
    } catch(\Tinify\ConnectionException $e) {
      $this->logger->error('TinyPNG - ConnectionException: Failed to download optimize image using TinyPNG due to "%error".', array('%error' => $e->getMessage()));
      // A network connection error occurred.
    } catch(Exception $e) {
      $this->logger->error('TinyPNG: Failed to download optimize image using TinyPNG due to "%error".', array('%error' => $e->getMessage()));
      // Something else went wrong, unrelated to the Tinify API.
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'api_key' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['api_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('TinyPNG API key'),
      '#description' => $this->t('Enter required TinyPNG API key. Get your API key from <a hre="http://tinypng.com" target="_blank">http://tinypng.com</a>'),
      '#default_value' => $this->configuration['api_key'],
      '#size' => 32,
      '#required' => TRUE
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['api_key'] = $form_state->getValue('api_key');
  }

}
