<?php

namespace Drupal\dom_bootstrap\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a text logo block.
 *
 * @Block(
 *   id = "dom_bootstrap_text_logo",
 *   admin_label = @Translation("Text Logo"),
 *   category = @Translation("DOM bootstrap")
 * )
 */
class TextLogoBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'bold' => '',
      'thin' => '',
      'include_mobile' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['bold'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bold part'),
      '#default_value' => $this->configuration['bold'],
    ];
    $form['thin'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Thin part'),
      '#default_value' => $this->configuration['thin'],
    ];
    $form['include_mobile'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include home icon for mobile'),
      '#default_value' => $this->configuration['include_mobile'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['bold'] = $form_state->getValue('bold');
    $this->configuration['thin'] = $form_state->getValue('thin');
    $this->configuration['include_mobile'] = $form_state->getValue('include_mobile');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [
      'bold_part' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->configuration['bold'],
        '#attributes' => ['class' => ['fs-2', 'fw-bold']],
      ],
      'thin_part' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->configuration['thin'],
        '#attributes' => ['class' => ['fs-2', 'fw-lighter']],
      ],
    ];
    if ($this->configuration['include_mobile']) {
      $build['bold_part']['#attributes']['class'][] = 'd-none';
      $build['bold_part']['#attributes']['class'][] = 'd-md-inline';
      $build['thin_part']['#attributes']['class'][] = 'd-none';
      $build['thin_part']['#attributes']['class'][] = 'd-md-inline';
      $build['mobile'] = [
        '#theme' => 'bootstrap_icon',
        '#icon' => 'house',
        '#attributes' => ['class' => ['h4', 'd-md-none']],
      ];
    }

    return $build;
  }

}
