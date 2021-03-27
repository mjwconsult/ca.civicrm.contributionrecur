<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

use CRM_Contributionrecur_ExtensionUtil as E;

class CRM_Core_Payment_RecurOfflineBasic extends CRM_Core_Payment {

  use CRM_Core_Payment_MJWTrait;

  protected $_mode = NULL;

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
   * @param array|\Civi\Payment\PropertyBag $params
   * @param string $component
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public function doPayment(&$params, $component = 'contribute') {
    $propertyBag = \Civi\Payment\PropertyBag::cast($params);

    // Set default contribution status
    $returnParams['payment_status_id'] = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');
    $returnParams['payment_status'] = 'Pending';

    if ($propertyBag->getIsRecur() && $propertyBag->getContributionRecurID()) {
      $reference = $propertyBag->getter('reference_id', TRUE);
      if ($reference) {
        // Save the external reference
        $recurParams = [
          'id' => $propertyBag->getContributionRecurID(),
          'trxn_id' => $reference,
          'processor_id' => $reference,
        ];
        civicrm_api3('ContributionRecur', 'create', $recurParams);
      }
    }

    // We always complete the first contribution as we are "adding" it.
    $returnParams['payment_status_id'] = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed');
    $returnParams['payment_status'] = 'Completed';
    $this->endDoPayment($returnParams);
    return $returnParams;
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
   * This function checks to see if we have the right config values
   *
   * @return string the error message if any
   * @public
   */
  function checkConfig() {
    return NULL;
  }

  /**
   * Does this processor support cancelling recurring contributions through code.
   *
   * If the processor returns true it must be possible to take action from within CiviCRM
   * that will result in no further payments being processed.
   *
   * @return bool
   */
  protected function supportsCancelRecurring() {
    return TRUE;
  }

  /**
   * Does the processor support the user having a choice as to whether to cancel the recurring with the processor?
   *
   * If this returns TRUE then there will be an option to send a cancellation request in the cancellation form.
   *
   * This would normally be false for processors where CiviCRM maintains the schedule.
   *
   * @return bool
   */
  protected function supportsCancelRecurringNotifyOptional() {
    return FALSE;
  }

}

