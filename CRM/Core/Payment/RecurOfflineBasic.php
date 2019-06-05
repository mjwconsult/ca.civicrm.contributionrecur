<?php
/*
 * Placeholder clas for offline recurring payments
 */

use CRM_Contributionrecur_ExtensionUtil as E;

class CRM_Core_Payment_RecurOfflineBasic extends CRM_Core_Payment {

  use CRM_Core_Payment_RecurOfflineTrait;

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
    $this->_processorName = $this->getPaymentTypeLabel();
  }

  /**
   * Override CRM_Core_Payment function
   *
   * @return string
   */
  public function getPaymentTypeName() {
    return 'credit_card';
  }

  /**
   * Override CRM_Core_Payment function
   *
   * @return string
   */
  public function getPaymentTypeLabel() {
    return E::ts('Recurring Offline Basic Processor');
  }

  /**
   * Override CRM_Core_Payment function
   *
   * @return array
   */
  public function getPaymentFormFields() {
    return [
      'reference_id',
    ];
  }

  /**
   * Return an array of all the details about the fields potentially required for payment fields.
   *
   * Only those determined by getPaymentFormFields will actually be assigned to the form
   *
   * @return array
   *   field metadata
   */
  public function getPaymentFormFieldsMetadata() {
    return [
      'reference_id' => [
        'htmlType' => 'text',
        'name' => 'reference_id',
        'title' => E::ts('Recurring Reference'),
        'attributes' => [
          'size' => 40,
          'maxlength' => 40,
          'autocomplete' => 'off',
        ],
        'is_required' => FALSE,
        'description' => 'Reference for the recurring contribution (eg. standing order number etc.)',
      ],
    ];
  }

  /**
   * @param  array $params assoc array of input parameters for this transaction
   *
   * @return array the result in a nice formatted array (or an error object)
   * @public
   */
  public function doPayment(&$params, $component = 'contribute') {
    // Set default contribution status
    $params['contribution_status_id'] = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');

    /**
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
*/
    if (!empty($params['is_recur']) && !empty($this->getRecurringContributionId($params))) {
      $reference = CRM_Utils_Array::value('reference_id', $params);
      if ($reference) {
        // Save the external reference
        $recurParams = [
          'id' => $this->getRecurringContributionId($params),
          'trxn_id' => $reference,
          'processor_id' => $reference,
        ];
        civicrm_api3('ContributionRecur', 'create', $recurParams);
      }
    }

    // We always complete the first contribution as we are "adding" it.
    $params['contribution_status_id'] = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed');
    // We need to set this to ensure that contributions are set to the correct status
    if (!empty($params['contribution_status_id'])) {
      $params['payment_status_id'] = $params['contribution_status_id'];
    }
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

