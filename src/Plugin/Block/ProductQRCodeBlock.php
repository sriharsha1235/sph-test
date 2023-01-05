<?php

namespace Drupal\sph_test\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\File\FileUrlGeneratorInterface;

/**
 * Provides a 'ProductQRCodeBlock' block.
 *
 * @Block(
 *  id = "product_qrcode_block",
 *  admin_label = @Translation("Product qrcode block"),
 * )
 */
class ProductQRCodeBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The config factory to get the installed themes.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * An extension discovery instance.
   *
   * @var \Drupal\Core\Extension\ThemeExtensionList
   */
  protected $themeList;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file_system service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Extension\ThemeExtensionList $theme_list
   *   The theme extension list.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file url generator.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, FileSystemInterface $file_system, ConfigFactoryInterface $config_factory, ThemeExtensionList $theme_list, FileUrlGeneratorInterface $file_url_generator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->fileSystem = $file_system;
    $this->configFactory = $config_factory;
    $this->themeList = $theme_list;
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('file_system'),
      $container->get('config.factory'),
      $container->get('extension.list.theme'),
      $container->get('file_url_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $node = $this->routeMatch->getParameter('node');
    $build = [];
    $public_path = $this->fileSystem->realpath('public://');
    $writer = new PngWriter();

    $default_theme_name = $this->configFactory->get('system.theme')->get('default');
    $theme_path = $this->themeList->getPath($default_theme_name);

    // Create QR code.
    $qrCode = QrCode::create($node->get('field_app_purchase_link')->getString())
      ->setEncoding(new Encoding('UTF-8'))
      ->setErrorCorrectionLevel(new ErrorCorrectionLevelLow())
      ->setSize(300)
      ->setMargin(10)
      ->setRoundBlockSizeMode(new RoundBlockSizeModeMargin())
      ->setForegroundColor(new Color(0, 0, 0))
      ->setBackgroundColor(new Color(255, 255, 255));

    if (!is_dir('public://qr-code/')) {
      $this->fileSystem->mkdir('public://qr-code');
    }
    $qr_image_path = $public_path . '/qr-code/' . $node->id() . '.png';
    $qr_image_path_full = $this->fileUrlGenerator->generateAbsoluteString('public://qr-code/' . $node->id() . '.png');

    // Create generic logo.
    $logo = Logo::create($theme_path . '/screenshot.png')->setResizeToWidth(50);

    // Create generic label.
    $label = Label::create('Jugaad Patches')
      ->setTextColor(new Color(255, 0, 0));

    $result = $writer->write($qrCode, $logo, $label);

    // Save it to a file.
    $result->saveToFile($qr_image_path);

    // Generate a data URI to include image data inline (i.e. inside an <img> tag)
    $dataUri = $result->getDataUri();
    $build['product_qrcode_block']['#markup'] = '<img src="' . $qr_image_path_full . '"/>';

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    if ($node = $this->routeMatch->getParameter('node')) {
      // If there is node add its cachetag.
      return Cache::mergeTags(parent::getCacheTags(), ['node:' . $node->id()]);
    }
    else {
      // Return default tags instead.
      return parent::getCacheTags();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

}
