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

class CRM_Contributionrecur_Generate {

  /**
   * @var int The payment processor IDs (array of live and test IDs)
   */
  protected $paymentProcessorIDs = [];

  public function __construct($paymentProcessorID) {
    $paymentProcessor = civicrm_api3('PaymentProcessor', 'getsingle', [
      'id' => $paymentProcessorID,
    ]);
    $paymentProcessors = civicrm_api3('PaymentProcessor', 'get', ['name' => $paymentProcessor['name']])['values'];
    foreach ($paymentProcessors as $id => $detail) {
      $this->paymentProcessorIDs[] = $id;
    }
  }

  /**
   * This calls Contribution.repeattransaction for all recurring contributions that
   * match the selected payment_processor_id and have a next_sched_contribution_date before today.
   *
   * @param int $recurringContributionID (Optional, if not set process all matching) recurring contribution
   *
   * @return array list of recur IDs that were processed
   * @throws \CRM_Core_Exception
   * @throws \CiviCRM_API3_Exception
   */
  public function generate($recurringContributionID = NULL) {
    $recurParams = [
      'payment_processor_id' => ['IN' => $this->paymentProcessorIDs],
      'options' => ['limit' => 0],
      'contribution_status_id' => 'In Progress',
    ];
    if ($recurringContributionID) {
      $recurParams['id'] = $recurringContributionID;
    }
    // Select all recurs with end date today or before
    $dtCurrentDay = date("Ymd") . '235959';
    $recurParams['next_sched_contribution_date'] = ['<=' => $dtCurrentDay];
    $listOfRecurs = civicrm_api3('ContributionRecur', 'get', $recurParams)['values'];

    foreach ($listOfRecurs as $recurID => $recurDetail) {
      if (empty($recurDetail['next_sched_contribution_date'])) {
        Throw new CRM_Core_Exception('Cannot generate repeat contribution if we have an empty next_sched_contribution_date');
      }
      $repeatContributionParams = [
        'contribution_status_id' => "Completed",
        'is_email_receipt' => 0,
        'receive_date' => $recurDetail['next_sched_contribution_date'],
        'contribution_recur_id' => $recurID,
      ];

      civicrm_api3('Contribution', 'repeattransaction', $repeatContributionParams);
      $updatedRecurs[] = $recurID;
      // @todo did this automatically update next_sched_contribution_date?
    }
    return $updatedRecurs ?? [];
  }

  /**
   * If the next_sched_contribution_date is empty for a recurring contribution update it to match
   * the previous contribution + recur period
   * @param int $recurringContributionID (Optional, if not set process all matching) recurring contribution
   *
   * @return array list of recur IDs that were processed
   * @throws \CRM_Core_Exception
   * @throws \CiviCRM_API3_Exception
   */
  public function fixNextScheduledDates($recurringContributionID) {
    $recurParams = [
      'payment_processor_id' => ['IN' => $this->paymentProcessorIDs],
      'options' => ['limit' => 0],
      'contribution_status_id' => 'In Progress',
    ];
    if ($recurringContributionID) {
      $recurParams['id'] = $recurringContributionID;
    }
    $listOfRecurs = civicrm_api3('ContributionRecur', 'get', $recurParams)['values'];

    foreach ($listOfRecurs as $recurID => $recurDetail) {
      $recurDetail['next_sched_contribution_date'] = '';
      if (empty($recurDetail['next_sched_contribution_date'])) {
        // Set to date of last contribution + 1 period
        try {
          $previousContribution = civicrm_api3('Contribution', 'getsingle', [
            'contribution_recur_id' => $recurID,
            'options' => ['sort' => "receive_date DESC", 'limit' => 1],
          ]);
        }
        catch (Exception $e) {
          \Civi::log()->debug("fixNextScheduledDates could not find contribution for recur {$recurID}. " . $e->getMessage());
          continue;
        }

        $nextScheduledTimestamp = strtotime("+{$recurDetail['frequency_interval']} {$recurDetail['frequency_unit']}", strtotime($previousContribution['receive_date']));
        $recurParams = ['id' => $recurID, 'next_sched_contribution_date' => date('YmdHis', $nextScheduledTimestamp)];
        civicrm_api3('ContributionRecur', 'create', $recurParams);
        $updatedRecurs[] = $recurID;
      }
    }
    return $updatedRecurs ?? [];
  }

}
