<?php

require_once 'contributionrecur.civix.php';
use CRM_Contributionrecur_ExtensionUtil as E;

/**
 * Implementation of hook_civicrm_config
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function contributionrecur_civicrm_config(&$config) {
  _contributionrecur_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_install
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function contributionrecur_civicrm_install() {
  _contributionrecur_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function contributionrecur_civicrm_uninstall() {
  _contributionrecur_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function contributionrecur_civicrm_enable() {
  _contributionrecur_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function contributionrecur_civicrm_disable() {
  _contributionrecur_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function contributionrecur_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _contributionrecur_civix_civicrm_upgrade($op, $queue);
}

/*
 * Put my settings page into the navigation menu
 */
function contributionrecur_civicrm_navigationMenu(&$navMenu) {
  $pages = array(
    'settings_page' => array(
      'label' => 'Recurring Contributions Settings',
      'name' => 'Recurring Contributions Settings',
      'url' => 'civicrm/admin/contribute/recursettings',
      'parent'    => array('Administer', 'CiviContribute'),
      'permission' => 'access CiviContribute,administer CiviCRM',
      'operator'   => 'AND',
      'separator'  => NULL,
      'active'     => 1
    ),
  );
  foreach ($pages as $item) {
    // Check that our item doesn't already exist.
    $menu_item_search = array('url' => $item['url']);
    $menu_items = array();
    CRM_Core_BAO_Navigation::retrieve($menu_item_search, $menu_items);
    if (empty($menu_items)) {
      $path = implode('/', $item['parent']);
      unset($item['parent']);
      _contributionrecur_civix_insert_navigation_menu($navMenu, $path, $item);
    }
  }
}

function contributionrecur_civicrm_varset($vars) {
  CRM_Core_Resources::singleton()->addVars('contributionrecur', $vars);
}

/**
 * hook_civicrm_buildForm
 *
 * @param string $formName
 * @param \CRM_Core_Form $form
 */
function contributionrecur_civicrm_buildForm($formName, &$form) {
  $fname = 'contributionrecur_'.$formName;
  if (function_exists($fname)) {
    $fname($form);
  }
}

/**
 * hook_civicrm_pageRun
 *
 * @param \CRM_Core_Page $page
 */
function contributionrecur_civicrm_pageRun(&$page) {
  $fname = 'contributionrecur_pageRun_'.$page->getVar('_name');
  if (function_exists($fname)) {
    $fname($page);
  }
}

/*
 * hook_civicrm_pre
 *
 * Intervene before recurring contribution records are created or edited, but only for my dummy processors.
 *
 * If the recurring days restriction settings are configured, then push the next scheduled contribution date forward to the first allowable one.
 * TODO: should there be cases where the next scheduled contribution is pulled forward? E.g. if it's still the next month and at least 15 days?
 */

function contributionrecur_civicrm_pre($op, $objectName, $objectId, &$params) {
  // since this function gets called a lot, quickly determine if I care about the record being created
  // watchdog('civicrm','hook_civicrm_pre for '.$objectName.' <pre>@params</pre>',array('@params' => print_r($params,TRUE)));
  switch($objectName) {
  case 'ContributionRecur':
      $settings = CRM_Core_BAO_Setting::getItem('Recurring Contributions Extension', 'contributionrecur_settings');
      if (!empty($params['payment_processor_id'])) {
        $pp_id = $params['payment_processor_id'];
        $class_name = _contributionrecur_pp_info($pp_id,'class_name');
        if ('Payment_RecurOffline' == substr($class_name,0,20)) {
          if ('create' == $op) {
            if (5 != $params['contribution_status_id'] && empty($params['next_sched_contribution_date'])) {
              $params['contribution_status_id'] = 5;
              // $params['trxn_id'] = NULL;
              $next = strtotime('+'.$params['frequency_interval'].' '.$params['frequency_unit']);
              $params['next_sched_contribution_date'] = date('YmdHis',$next);
            }
            if ('Payment_RecurOfflineACHEFT' == $class_name) {
              $params['payment_instrument_id'] = 5;
            }
          }
          if (!empty($params['next_sched_contribution_date'])) {
            $allow_days = empty($settings['days']) ? array('-1') : $settings['days'];
            if (0 < max($allow_days)) {
              $init_time = ('create' == $op) ? time() : strtotime($params['next_sched_contribution_date']);
              $from_time = _contributionrecur_next($init_time,$allow_days);
              $params['next_sched_contribution_date'] = date('YmdHis', $from_time);
            }
          }
        }
      }
      if (empty($params['installments'])) {
        $params['installments'] = '0';
      }
      if (!empty($settings['no_receipts'])) {
        $params['is_email_receipt'] = 0;
      }
      break;
    case 'Contribution':
      if (!empty($params['contribution_recur_id'])) {
        $pp_id = _contributionrecur_payment_processor_id($params['contribution_recur_id']);
        if ($pp_id) {
          $class_name = _contributionrecur_pp_info($pp_id,'class_name');
          if ('create' == $op && 'Payment_RecurOffline' == substr($class_name,0,20)) {
            if ('Payment_RecurOfflineACHEFT' == $class_name) {
              $params['payment_instrument_id'] = 5;
            }
            $settings = civicrm_api3('Setting', 'getvalue', array('name' => 'contributionrecur_settings'));
            $allow_days = empty($settings['days']) ? array('-1') : $settings['days'];
            if (0 < max($allow_days)) {
              $from_time = _contributionrecur_next(strtotime($params['receive_date']),$allow_days);
              $params['receive_date'] = date('Ymd', $from_time).'030000';
            }
          }
        }
      }
      break;
  }
}

/**
 * Implementation of hook_civicrm_validateForm().
 *
 * Prevent server validation of cc fields for my dummy cc processor
 *
 * @param $formName - the name of the form
 * @param $fields - Array of name value pairs for all 'POST'ed form values
 * @param $files - Array of file properties as sent by PHP POST protocol
 * @param $form - reference to the form object
 * @param $errors - Reference to the errors array.
 */
function contributionrecur_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors) {
  if (isset($form->_paymentProcessor['class_name'])) {
    if ($form->_paymentProcessor['class_name'] == 'Payment_RecurOffline') {
      foreach(array('credit_card_number','cvv2') as $elementName) {
        if ($form->elementExists($elementName)){
          $element = $form->getElement($elementName);
          $form->removeElement($elementName, true);
          $form->addElement($element);
        }
      }
    }
    elseif ($form->_paymentProcessor['class_name'] == 'Payment_RecurOfflineACHEFT') {
      foreach(array('account_holder','bank_account_number','bank_identification_number','bank_name') as $elementName) {
        if ($form->elementExists($elementName)){
          $element = $form->getElement($elementName);
          $form->removeElement($elementName, true);
          $form->addElement($element);
        }
      }
    }
  }
}

/*
 * The contribution itself doesn't tell you which payment processor it came from
 * So we have to dig back via the contribution_recur_id that it is associated with.
 */
function _contributionrecur_payment_processor_id($contribution_recur_id) {
  $params = array(
    'sequential' => 1,
    'id' => $contribution_recur_id,
    'return' => 'payment_processor_id'
  );
  $result = civicrm_api3('ContributionRecur', 'getvalue', $params);
  if (empty($result)) {
    return FALSE;
    // TODO: log error
  }
  return $result;
}

/*
 * See if I need to fix the payment instrument by looking for
 * my offline recurring acheft processor
 * I'm assuming that other type 2 processors take care of themselves,
 * but you could remove class_name to fix them also
 */
function _contributionrecur_pp_info($payment_processor_id, $return, $class_name = NULL) {
  $params = array(
    'sequential' => 1,
    'id' => $payment_processor_id,
    'return' => $return
  );
  if (!empty($class_name)) {
    $params['class_name'] = $class_name;
  }
  $result = civicrm_api3('PaymentProcessor', 'getvalue', $params);
  if (empty($result)) {
    return FALSE;
    // TODO: log error
  }
  return $result;
}

/**
 * function _contributionrecur_next
 *
 * @param $from_time: a unix time stamp, the function returns values greater than this
 * @param $days: an array of allowable days of the month
 *
 * A utility function to calculate the next available allowable day, starting from $from_time.
 * Strategy: increment the from_time by one day until the day of the month matches one of my available days of the month.
 *
 * @return float|int
 */
function _contributionrecur_next($from_time, $allow_mdays) {
  $dp = getdate($from_time);
  $i = 0;  // so I don't get into an infinite loop somehow
  while(($i++ < 60) && !in_array($dp['mday'],$allow_mdays)) {
    $from_time += (24 * 60 * 60);
    $dp = getdate($from_time);
  }
  return $from_time;
}

/**
 * hook_civicrm_buildForm for back-end contribution forms
 *
 * Allow editing of contribution amounts!
 * @param \CRM_Core_Form $form
 */
function contributionrecur_CRM_Contribute_Form_Contribution(&$form) {
  // ignore this form unless I'm editing an contribution from my offline payment processor
  if (empty($form->_values['contribution_recur_id'])) {
    return;
  }
  $recur_id = $form->_values['contribution_recur_id'];
  $pp_id = _contributionrecur_payment_processor_id($recur_id);
  if ($pp_id) {
    $class_name = _contributionrecur_pp_info($pp_id,'class_name');
    if ('Payment_RecurOffline' == substr($class_name,0,20)) {
      foreach(array('fee_amount','net_amount') as $elementName) {
        if ($form->elementExists($elementName)){
          $form->getElement($elementName)->unfreeze();
        }
      }
    }
  }
}

/**
 * hook_civicrm_buildForm for public ("front-end") contribution forms
 *
 * Force recurring if it's an option on this form and configured in the settings
 * Add information about the next contribution if the allowed days are configured
 *
 * @param \CRM_Contribute_Form_Contribution_Main $form
 *
 * @throws \CRM_Core_Exception
 */
function contributionrecur_CRM_Contribute_Form_Contribution_Main(&$form) {
  // ignore this form if I have no payment processor or there's no recurring option
  if (empty($form->_paymentProcessors)) {
    return;
  }
  // if I'm using my dummy cc processor, modify the billing fields
  switch(CRM_Utils_Array::value('class_name', $form->_paymentProcessor)) {
    case 'Payment_RecurOffline': // cc offline
      $form->removeElement('credit_card_number',TRUE);
      // unset($form->_paymentFields['credit_card_number']);
      $form->addElement('text','credit_card_number',ts('Credit Card, last 4 digits'));
      $form->removeElement('cvv2',TRUE);
      unset($form->_paymentFields['cvv2']);
      break;
  }

  if (empty($form->_elementIndex['is_recur'])) {
    return;
  }
  $settings = CRM_Core_BAO_Setting::getItem('Recurring Contributions Extension', 'contributionrecur_settings');
  $page_id = $form->getVar('_id');
  $page_settings = CRM_Core_BAO_Setting::getItem('Recurring Contributions Extension', 'contributionrecur_settings_'.$page_id);
  foreach(array('force_recur','nice_recur') as $setting) {
    if (!empty($page_settings[$setting])) {
      $settings[$setting] = ($page_settings[$setting] > 0) ? 1 : 0;
    }
  }
  // if the site administrator has enabled forced recurring pages
  if (!empty($settings['force_recur'])) {
    // If a form enables recurring, and the force_recur setting is on, set recurring to the default and required
    $form->setDefaults(array('is_recur' => 1)); // make recurring contrib default to true
    $form->addRule('is_recur', ts('You can only use this form to make recurring contributions.'), 'required');
    contributionrecur_civicrm_varset(array('forceRecur' => '1'));
  }
  elseif (!empty($settings['nice_recur'])) {
    CRM_Core_Resources::singleton()->addStyleFile('ca.civicrm.contributionrecur', 'css/donation.css');
    CRM_Core_Resources::singleton()->addScriptFile('ca.civicrm.contributionrecur', 'js/donation.js');
    $form->setDefaults(array('is_recur' => 1)); // make recurring contrib default to true
  }
  // if the site administrator has resticted the recurring days
  $allow_days = empty($settings['days']) ? array('-1') : $settings['days'];
  if (max($allow_days) > 0) {
    $next_time = _contributionrecur_next(strtotime('+1 day'),$allow_days);
    contributionrecur_civicrm_varset(array('nextDate' => date('Y-m-d', $next_time)));
  }
  if ((max($allow_days) > 0) || !empty($settings['force_recur'])) {
    CRM_Core_Resources::singleton()->addScriptFile('ca.civicrm.contributionrecur', 'js/front.js');
  }

}

/**
 * add some functionality to the update subscription form for recurring contributions
 *
 * Todo: make the available new fields configurable
 *
 * @param \CRM_Core_Form $form
 *
 * @throws \CRM_Core_Exception
 * @throws \CiviCRM_API3_Exception
 */
function contributionrecur_CRM_Contribute_Form_UpdateSubscription(&$form) {
  // only do this if the user is allowed to edit contributions. A more stringent permission might be smart.
  if (!CRM_Core_Permission::check('edit contributions')) {
    return;
  }
  $settings = civicrm_api3('Setting', 'getvalue', array('name' => 'contributionrecur_settings'));
  // don't do this unless the site administrator has enabled it
  if (empty($settings['edit_extra'])) {
    return;
  }
  $allow_days = empty($settings['days']) ? array('-1') : $settings['days'];
  if (0 < max($allow_days)) {
    $userAlert = ts('Your next scheduled contribution date will automatically be updated to the next allowable day of the month: %1',array(1 => implode(',',$allow_days)));
    CRM_Core_Session::setStatus($userAlert, ts('Warning'), 'alert');
  }
  $crid = CRM_Utils_Request::retrieve('crid', 'Integer', $form, FALSE);
  /* get the recurring contribution record and the contact record, or quit */
  try {
    $recur = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $crid));
  }
  catch (CiviCRM_API3_Exception $e) {
    return;
  }
  try {
    $contact = civicrm_api3('Contact', 'getsingle', array('id' => $recur['contact_id']));
  }
  catch (CiviCRM_API3_Exception $e) {
    return;
  }
  // turn off default notification checkbox, most will want to hide it as well.
  $defaults = array('is_notify' => 0);
  $edit_fields = array(
    'contribution_status_id' => 'Status',
    'next_sched_contribution_date' => 'Next Scheduled Contribution',
    'start_date' => 'Start Date',
  );
  foreach(array_keys($edit_fields) as $fid) {
    if ($form->elementExists($fid)) {
      unset($edit_fields[$fid]);
    }
    else {
      $defaults[$fid] = $recur[$fid];
    }
  }
  if (0 == count($edit_fields)) { // assume everything is taken care of
    return;
  }
  $form->addElement('static','contact',$contact['display_name']);
  // $form->addElement('static','contact',$contact['display_name']);
  if ($edit_fields['contribution_status_id']) {
    $contributionStatus = CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name');
    $form->addElement('select', 'contribution_status_id', ts('Status'),$contributionStatus);
    unset($edit_fields['contribution_status_id']);
  }
  foreach($edit_fields as $fid => $label) {
    $form->addDateTime($fid,ts($label));
  }
  $form->setDefaults($defaults);
  // now add some more fields for display only
  $pp_label = $form->_paymentProcessor['name']; // get my pp
  $form->addElement('static','payment_processor',$pp_label);
  $label = CRM_Contribute_Pseudoconstant::financialType($recur['financial_type_id']);
  $form->addElement('static','financial_type',$label);
  $labels = CRM_Contribute_Pseudoconstant::paymentInstrument();
  $label = $labels[$recur['payment_instrument_id']];
  $form->addElement('static','payment_instrument',$label);
  $form->addElement('static','failure_count',$recur['failure_count']);
  CRM_Core_Region::instance('page-body')->add(array(
    'template' => 'CRM/Contributionrecur/Subscription.tpl',
  ));
  CRM_Core_Resources::singleton()->addScriptFile('ca.civicrm.contributionrecur', 'js/subscription.js');
}

/*
 *  Provide edit link for cancelled recurring contributions, allowing uncancel */
function contributionrecur_CRM_Contribute_Form_Search(&$form) {
  CRM_Core_Resources::singleton()->addScriptFile('ca.civicrm.contributionrecur', 'js/subscription_uncancel.js');
}

/*
 * Display extra info on the recurring contribution view
 */
function contributionrecur_pageRun_CRM_Contribute_Page_ContributionRecur($page) {
  // get the recurring contribution record or quit
  $crid = CRM_Utils_Request::retrieve('id', 'Integer', $page, FALSE);
  try {
    $recur = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $crid));
  }
  catch (CiviCRM_API3_Exception $e) {
    return;
  }
  // add the 'generate ad hoc contribution form' link
  $template = CRM_Core_Smarty::singleton();
  $adHocContributionLink = CRM_Utils_System::url('civicrm/contact/contributionrecur_adhoc', 'reset=1&cid='.$recur['contact_id'].'&paymentProcessorId='.$recur['payment_processor_id'].'&crid='.$crid.'&is_test='.$recur['is_test']);
  $template->assign('adHocContributionLink',
    '<a href="'.$adHocContributionLink.'">Generate</a>'
  );
  CRM_Core_Region::instance('page-body')->add(array(
    'template' => 'CRM/Contributionrecur/ContributionRecur.tpl',
  ));
  CRM_Core_Resources::singleton()->addScriptFile('ca.civicrm.contributionrecur', 'js/subscription_view.js');
}

/*
 * Add js to the summary page so it can be used on the financial/contribution tab */
function contributionrecur_pageRun_CRM_Contact_Page_View_Summary($page) {
  $contactId = CRM_Utils_Request::retrieve('cid', 'Positive');
  $recur_edit_url = CRM_Utils_System::url('civicrm/contribute/updaterecur','reset=1&action=update&context=contribution&cid='.$contactId.'&crid=');
  contributionrecur_civicrm_varset(array('recur_edit_url' => $recur_edit_url));
}

/**
 * Implement hook_civicrm_searchTasks()
 *
 * Enable a simpler completion of pending contributions without sending emails, etc.
 */
function contributionrecur_civicrm_searchTasks($objectType, &$tasks ) {
  if ( $objectType == 'contribution' && CRM_Core_Permission::check('edit contributions')) {
    $tasks[] = array (
      'title' => ts('Convert Pending Offline Contributions to Completed', array('domain' => 'ca.civicrm.contributionrecur')),
      'class' => 'CRM_Contributionrecur_Task_CompletePending',
      'result' => TRUE);
  }
  elseif ( $objectType == 'contact' && CRM_Core_Permission::check('edit contributions')) {
    $tasks[] = array (
      'title' => ts('Generate Reversing Membership Payments', array('domain' => 'ca.civicrm.contributionrecur')),
      'class' => 'CRM_Contributionrecur_Task_MembershipPayments',
      'result' => TRUE);
  }
}

function _contributionrecur_get_iats_extra($recur) {
  if (empty($recur['id']) && empty($recur['invoice_id'])) {
    return;
  }
  $extra = array();
  $params = array(1 => array('civicrm_iats_customer_codes', 'String'));
  $dao = CRM_Core_DAO::executeQuery("SHOW TABLES LIKE %1", $params);
  if (!empty($recur['id']) && $dao->fetch()) {
    $params = array(1 => array($recur['id'],'Integer'));
    $dao = CRM_Core_DAO::executeQuery("SELECT expiry FROM civicrm_iats_customer_codes WHERE recur_id = %1", $params);
    if ($dao->fetch()) {
      $expiry = str_split($dao->expiry,2);
      $extra['expiry'] = '20'.implode('-',$expiry);
    }
  }
  $params = array(1 => array('civicrm_iats_request_log', 'String'));
  $dao = CRM_Core_DAO::executeQuery("SHOW TABLES LIKE %1", $params);
  if (!empty($recur['invoice_id']) && $dao->fetch()) {
    $params = array(1 => array($recur['invoice_id'],'String'));
    $dao = CRM_Core_DAO::executeQuery("SELECT cc FROM civicrm_iats_request_log WHERE invoice_num = %1", $params);
    if ($dao->fetch()) {
      $extra['cc'] = $dao->cc;
    }
  }
  return $extra;
}

/**
 * For a given recurring contribution, find a reasonable candidate for a template, where possible
 */
function _contributionrecur_civicrm_getContributionTemplate($contribution) {
  // Get the most recent contribution in this series that matches the same total_amount, if present
  $template = array();
  $get = array('contribution_recur_id' => $contribution['contribution_recur_id'], 'options'  => array('sort'  => ' id DESC' , 'limit'  => 1));
  if (!empty($contribution['total_amount'])) {
    $get['total_amount'] = $contribution['total_amount'];
  }
  $result = civicrm_api3('contribution', 'get', $get);
  if (!empty($result['values'])) {
    $contribution_ids = array_keys($result['values']);
    $template = $result['values'][$contribution_ids[0]];
    $template['line_items'] = array();
    $get = array('entity_table' => 'civicrm_contribution', 'entity_id' => $contribution_ids[0]);
    $result = civicrm_api3('LineItem', 'get', $get);
    if (!empty($result['values'])) {
      foreach($result['values'] as $initial_line_item) {
        $line_item = array();
        foreach(array('price_field_id','qty','line_total','unit_price','label','price_field_value_id','financial_type_id') as $key) {
          $line_item[$key] = $initial_line_item[$key];
        }
        $template['line_items'] = $line_item;
      }
    }
  }
  return $template;
}

function contributionrecur_civicrm_tabset($tabsetName, &$tabs, $context) {
  //check if the tabset is Contribution Page
  if ($tabsetName == 'civicrm/admin/contribute') {
    if (!empty($context['contribution_page_id'])) {
      $contribID = $context['contribution_page_id'];
      $url = CRM_Utils_System::url( 'civicrm/admin/contribute/recur',
        "reset=1&snippet=5&force=1&id=$contribID&action=update&component=contribution" );
      //add a new Volunteer tab along with url
      $tab['recur'] = array(
        'title' => ts('Recurring'),
        'link' => $url,
        'valid' => 1,
        'active' => 1,
        'current' => false,
      );
    }
    if (!empty($context['urlString']) && !empty($context['urlParams'])) {
      $tab[] = array(
        'title' => ts('Recurring'),
        'name' => ts('Recurring'),
        'url' => $context['urlString'] . 'recur',
        'qs' => $context['urlParams'],
        'uniqueName' => 'recur',
      );
    }
    //Insert this tab into position 4
    $tabs = array_merge(
      array_slice($tabs, 0, 4),
      $tab,
      array_slice($tabs, 4)
    );
  }
}

function contributionrecur_civicrm_buildAmount($pageType, &$form, &$amount) {
  if (empty($form->_values['fee'])) {
    return;
  }
  foreach ($form->_values['fee'] as $fieldId => $fieldDetail) {
    if ($fieldDetail['name'] === 'other_amount') {
      if (CRM_Utils_Request::retrieveValue('fixed_amount', 'Float')) {
        $form->setDefaults(["price_{$fieldId}" => CRM_Utils_Request::retrieve('fixed_amount', 'Float')]);
        CRM_Core_Resources::singleton()
          ->addScriptFile(E::LONG_NAME, 'js/hideotheramount.js');
      }
    }
  }
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postInstall
 */
function contributionrecur_civicrm_postInstall() {
  _contributionrecur_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
 */
function contributionrecur_civicrm_entityTypes(&$entityTypes) {
  _contributionrecur_civix_civicrm_entityTypes($entityTypes);
}
