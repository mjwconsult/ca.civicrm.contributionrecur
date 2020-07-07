<?php
/*
 * Placeholder clas for offline recurring payments
 */

class CRM_Core_Payment_RecurOffline extends CRM_Core_Payment {

  protected $_mode = NULL;

  protected $_params = [];

  /**
   * Constructor
   *
   * @param string $mode the mode of operation: live or test
   *
   * @return void
   */
  public function __construct($mode, &$paymentProcessor) {
    $this->_mode = $mode;
    $this->_paymentProcessor = $paymentProcessor;
  }

  /**
   * @param array|\Civi\Payment\PropertyBag $params
   * @param string $component
   *
   * @return array
   */
  public function doPayment(&$params, $component = 'contribute') {
    if ($this->_mode == 'test') {
      $query             = "SELECT MAX(trxn_id) FROM civicrm_contribution WHERE trxn_id LIKE 'test\\_%'";
      $p                 = array();
      $trxn_id           = strval(CRM_Core_Dao::singleValueQuery($query, $p));
      $trxn_id           = str_replace('test_', '', $trxn_id);
      $trxn_id           = intval($trxn_id) + 1;
      $params['trxn_id'] = sprintf('test_%08d', $trxn_id);
    }
    else {
      $query             = "SELECT MAX(trxn_id) FROM civicrm_contribution WHERE trxn_id LIKE 'live_%'";
      $p                 = array();
      $trxn_id           = strval(CRM_Core_Dao::singleValueQuery($query, $p));
      $trxn_id           = str_replace('live_', '', $trxn_id);
      $trxn_id           = intval($trxn_id) + 1;
      $params['trxn_id'] = sprintf('live_%08d', $trxn_id);
    }
    $params['payment_status_id'] = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');
    return $params;
  }

  /**
   * Are back office payments supported.
   *
   * @return bool
   */
  protected function supportsBackOffice() {
    return TRUE;
  }

  /** 
   * No cc form fields!
   *
   */
  public function getPaymentFormFields() {
    return [];
  }

  /**
   * @param string $message
   * @param array $params
   *
   * @return bool
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function changeSubscriptionAmount(&$message = '', $params = []) {
    $message = 'The recurring amount has been changed in CiviCRM. This has not changed the offline process.';
    return TRUE;
  }

  function &error($errorCode = NULL, $errorMessage = NULL) {
    $e = CRM_Core_Error::singleton();
    if ($errorCode) {
      $e->push($errorCode, 0, NULL, $errorMessage);
    }
    else {
      $e->push(9001, 0, NULL, 'Unknown System Error.');
    }
    return $e;
  }

  /**
   * This function checks to see if we have the right config values
   *
   * @return string the error message if any
   * @public
   */
  function checkConfig() {
    return NULL;
  }
}

