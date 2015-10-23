<?php
/**
 * Implementation of contributionrecur_membershipImplicit
 *
 * Process the potential implicit membership contributions in $contact
 * $contact is an array with keys:
 * membership_type_id, contact_id, contribution_ids
 * contribution_ids is an array of contributions with keys contribution id and array values receive date and total_amount
 * 
 * This is an internal function and requires the calling function to do any sanity checks, etc.
 *
 * The fourth optional parameter is a financial type id that contributions are converted to to conver the minimum membership cost
 * This is useful for example to allow the default financial type to be tax deductible, with the membership portion converted to being non-tax-deductible
 * It will only process pending contributions in this way.
 */
function contributionrecur_membershipImplicit($contact, $contributions, $membership_types, $membership_financial_type_id = NULL) {

  $return[] = $contributions;
  // watchdog('contributionrecur','running membership implicit function for '.$contact['contact_id'].', '.$op.', <pre>@params</pre>',array('@params' => print_r($params, TRUE)));
  $contact_id = $contact['contact_id'];
  // print_r($membership_types);
  // only proceed if this contact has exactly one membership of the right kind and status (grace or lapsed)
  $p = array('contact_id' => $contact_id, 'status_id' => array('IN' => array(3,4)), 'membership_type_id' => array('IN' => array_keys($membership_types)));
  try{
    $membership = civicrm_api3('Membership', 'getsingle', $p);
    $membership_type = $membership_types[$membership['membership_type_id']];
    $total_amount = floatval(0);
    $applied_contributions = array();
    $start_date = '';
    do {
      $contribution = array_shift($contributions);
      $total_amount += $contribution['total_amount'];
      $start_date = max($start_date,$contribution['receive_date']);
      $applied_contributions[] = $contribution;
    } while (
      (empty($membership_financial_type_id) || ($total_amount < floatval($membership_type['minimum_fee'])))
       && count($contributions)
    );
    // are contributions enough to renew the membership?
    if ($total_amount < $membership_type['minimum_fee']) {
      return array('Total amount < minimum fee');
    }
    // figure out start and end dates of the membership and update it
    // $start_date = date('Y-m-d'); 
    $updated_membership = array('contact_id' => $contact_id, 'id' => $membership['id']);
    $dates = CRM_Member_BAO_MembershipType::getRenewalDatesForMembershipType($membership['id'],date('YmdHis',strtotime($start_date)),$membership['membership_type_id'],1);
    $updated_membership['start_date'] = CRM_Utils_Array::value('start_date', $dates);
    $updated_membership['end_date'] = CRM_Utils_Array::value('end_date', $dates);
    $updated_membership['source'] = ts('Auto-renewed membership from contribution of implicit membership type');
    $updated_membership['status_id'] = 2; // always set to current now
    civicrm_api3('Membership','create',$updated_membership);

    // now assign all the applied contributions to this membership
    foreach($applied_contributions as $contribution) {
      civicrm_api3('MembershipPayment','create', array('contribution_id' => $contribution['id'], 'membership_id' => $membership['id']));
    }
    // see if we need/can convert some of these to membership contributions, and then generate reversing contributions for the rest
    if (!empty($membership_financial_type_id)) {
      $membership_amount = $membership_type['minimum_fee'];
      // save some details from my last contribution
      $last_contribution = end($applied_contributions);
      // first try and change the financial type of any pending contributions, but no more than the membership minimum fee
      foreach($applied_contributions as $i =>  $contribution) {
        if ($contribution['contribution_status_id'] == 2 && (($membership_amount - $contribution['total_amount']) > 0)) {
          $p = array('id' => $contribution['id'], 'financial_type_id' => $membership_financial_type_id);
          try {
            civicrm_api3('Contribution', 'create', $p);
            $membership_amount = $membership_amount - $contribution['total_amount'];
            unset($applied_contributions[$i]);
            $return[] = 'Converted contribution '. $contribution['id'] .' of contact id '. $contact_id .' to financial type id '. $membership_financial_type_id;
          }
          catch (CiviCRM_API3_Exception $e) {
            $return[] = $e->getMessage();
            $return[] = $p;
          }
        }
      }
      if ($membership_amount > 0) { // create matching contribution and reversal based on the last contribution
        // get details of last contribution
        $params = array('version' => 3, 'sequential' => 1, 'id' => $last_contribution['id']);
        $contribution = civicrm_api3('Contribution', 'getsingle', $params);
        $hash = md5(uniqid(rand(), true));
        $membership_contribution = array(
          'version'        => 3,
          'contact_id'       => $contact_id,
          'receive_date'       => $contribution['receive_date'], 
          'total_amount'       => $membership_amount,
          'payment_instrument_id'  => $contribution['payment_instrument_id'],
          'contribution_recur_id'  => $contribution['contribution_recur_id'],
          'trxn_id'        => $hash, /* placeholder: just something unique that can also be seen as the same as invoice_id */
          'invoice_id'       => $hash,
          'source'         => 'Implicit membership account transfer',
          'contribution_status_id' => 1, 
          'currency'  => $contribution['currency'],
          'payment_processor'   => $contribution['payment_processor'],
          'financial_type_id' => $membership_financial_type_id,
        );
        $reversal_contribution = $membership_contribution;
        $reversal_contribution['total_amount'] = -$membership_amount;
        $reversal_contribution['financial_type_id'] = $contribution['financial_type_id'];
        $reversal_contribution['trxn_id'] = $reversal_contribution['invoice_id'] = md5(uniqid(rand(), true));
        try {
          civicrm_api3('Contribution', 'create', $membership_contribution);
          civicrm_api3('Contribution', 'create', $reversal_contribution);
          $return[] = 'Created membership and reversal contributions for contact id '. $contact_id;
        }
        catch (CiviCRM_API3_Exception $e) {
          $return[] = $e->getMessage();
          $return[] = $p;
        }
      }
    }
    // now see if we should be generating an activity record
    $settings = civicrm_api3('Setting', 'getvalue', array('name' => 'contributionrecur_settings'));
    $activity_type_id = $settings['activity_type_id'];
    if ($activity_type_id > 0) {
      civicrm_api3('Activity', 'create', array(
        'version'       => 3,
        'activity_type_id'  => $activity_type_id,
        'source_contact_id'   => $contact_id,
        /* 'source_record_id' => $membership['id'], */
        'subject'       => "Applied unallocated contributions to membership using implicit membership from contributions rule.",
        'status_id'       => 2,
        'activity_date_time'  => date("YmdHis"),)
      );
    }
    $return[] = 'Updated membership '.$membership['id'];
  }
  catch (CiviCRM_API3_Exception $e) {
    $return[] = $e->getMessage();
    $return[] = $p;
  }
  return $return;
}

