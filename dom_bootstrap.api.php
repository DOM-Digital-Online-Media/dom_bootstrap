<?php

/**
 * @file
 * Hooks specific to the dom bootstrap module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Provide an ability to add dynamic items into the collapsible menu.
 *
 * @param array $build
 *   Builded menu tree elements array.
 */
function hook_dom_bootstrap_collapsible_menu(array &$build) {
  $build['#items']['put_delimiter'] = TRUE;
  $build['#cache']['max-age'] = -1;
}

/**
 * @} End of "addtogroup hooks".
 */
