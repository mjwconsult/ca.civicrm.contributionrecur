<?php

/**
 * ContributionRecur.Generatenextcontribution API specification
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRM/API+Architecture+Standards
 */
function _civicrm_api3_contribution_recur_generatenextcontribution_spec(&$spec) {
  $spec['payment_processor_id'] = [
    'type' => CRM_Utils_Type::T_INT,
    'title' => ts('Payment Processor ID'),
    'description' => 'Foreign key to civicrm_payment_processor.id',
    'pseudoconstant' => [
      'table' => 'civicrm_payment_processor',
      'keyColumn' => 'id',
      'labelColumn' => 'name',
    ],
    'api.required' => TRUE,
  ];
  $spec['contribution_recur_id'] = [
    'type' => CRM_Utils_Type::T_INT,
    'title' => ts('Contribution Recur ID'),
    'description' => 'Contribution Recur ID',
    'FKApiName' => 'ContributionRecur',
    'FKClassName' => 'CRM_Contribute_BAO_ContributionRecur',
    'api.required' => FALSE,
  ];
}

/**
 * ContributionRecur.Generatenextcontribution API
 * @param array $params
 *
 * @return array|void
 * @throws \CRM_Core_Exception
 * @throws \CiviCRM_API3_Exception
 */
function civicrm_api3_contribution_recur_generatenextcontribution($params) {
  if (empty($params['payment_processor_id'])) {
    Throw new CiviCRM_API3_Exception('Missing required parameter: payment_processor_id');
  }
  $recurGenerate = new CRM_Contributionrecur_Generate($params['payment_processor_id']);
  $recurIDsUpdated = $recurGenerate->generate($params['contribution_recur_id'] ?? NULL);
  return civicrm_api3_create_success($recurIDsUpdated, $params, 'ContributionRecur', 'Generate');
}
