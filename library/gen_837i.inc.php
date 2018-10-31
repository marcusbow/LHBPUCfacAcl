<?php
/** 
* library/gen_837I.inc.php Genetating a UB04 Electronic transmission file. 
* 
* Program for Generating an Electronic transmission
* file of the UB-04 (CMS 1450)
* 
* Copyright (C) 2014-2017 Terry Hill <teryhill@librehealth.io> 
* 
* LICENSE: This Source Code is subject to the terms of the Mozilla Public License, v. 2.0.
* See the Mozilla Public License for more details.
* If a copy of the MPL was not distributed with this file, You can obtain one at https://mozilla.org/MPL/2.0/.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
*
* @package LibreHealth EHR
* @author Terry Hill <teryhill@librehealth.io>
* @link http://librehealth.io
*
* Please help the overall project by sending changes you make to the authors and to the LibreHealth EHR community.
*
*/

$fake_register_globals=false;
$sanitize_all_escapes=true;

require_once("Claim.class.php");


function gen_837I($pid, $encounter, &$log, $encounter_claim=false) {

  $today = time();
  $out = '';
  $claim = new Claim($pid, $encounter);
  $edicount = 0;

  // This is true for the 5010 standard, false for 4010.
  // x12gsversionstring() should be "005010X222A1" or "004010X098A1".
  $CMS_5010 = strpos($claim->x12gsversionstring(), '5010') !== false;

  $log .= "Generating claim $pid-$encounter for " .
    $claim->patientFirstName()  . ' ' .
    $claim->patientMiddleName() . ' ' .
    $claim->patientLastName()   . ' on ' .
    date('Y-m-d H:i', $today) . ".\n";



  $out .= "ISA" .
    "*" . $claim->x12gsisa01() .
    "*" . $claim->x12gsisa02() .
    "*" . $claim->x12gsisa03() .
    "*" . $claim->x12gsisa04() .
    "*" . $claim->x12gsisa05() .
    "*" . $claim->x12gssenderid() .
    "*" . $claim->x12gsisa07() .
    "*" . $claim->x12gsreceiverid() .
	// date of transmission "*030911" .
	"*" . date('Ymd', $today) .
    //Time of transmission "*1630" .
	"*" . date('Hi', $today) .
	"*" . ($CMS_5010 ? "^"     : "U"    ) .
    "*" . ($CMS_5010 ? "00501" : "00401") .
    "*000000001" .
    "*" . $claim->x12gsisa14() .
    "*" . $claim->x12gsisa15() .
    "*:" .
    "~\n";

  $out .= "GS" .
    "*HC" .
    "*" . $claim->x12gsgs02() .
    "*" . trim($claim->x12gs03()) .
    "*" . date('Ymd', $today) .
    "*" . date('Hi', $today) .
    "*1" .
    "*X" .
   // "*" . $claim->x12gsversionstring() . 
    "*" . "005010X223A2".
    "~\n";

  ++$edicount;
  $out .= "ST" .
    "*837" .
    "*0021" .
    //($CMS_5010 ? ("*" . $claim->x12gsversionstring()) : "") .
	"*" . "005010X223A2".
    "~\n";

  ++$edicount;
  $out .= "BHT" .
    "*0019" .                             // 0019 is required here
    "*00" .                               // 00 = original transmission
    "*0123" .                             // reference identification
    "*" . date('Ymd', $today) .           // transaction creation date
    "*" . date('Hi', $today) .            // transaction creation time
    ($encounter_claim ? "*RP" : "*CH") .  // RP = reporting, CH = chargeable
    "~\n";

  if (!$CMS_5010) {
    // This segment was deleted for 5010.
    ++$edicount;
    $out .= "REF" .
      "*87" .
      "*" . $claim->x12gsversionstring() .
      "~\n";
  }

  ++$edicount;
  //Field length is limited to 35. See nucc dataset page 63 www.nucc.org
  $billingFacilityName = substr($claim->billingFacilityName(), 0, $CMS_5010 ? 60 : 35);
  $out .= "NM1" .       // Loop 1000A Submitter stays in the 837I
    "*41" .
    "*2" .
    "*" . $billingFacilityName .
    "*" .
    "*" .
    "*" .
    "*" .
    "*46";
   if (trim($claim->x12gsreceiverid()) == '470819582') { // if ECLAIMS EDI
    $out  .=  "*" . $claim->clearingHouseETIN();
   } else {
    $out  .=  "*" . $claim->billingFacilityETIN();
   }
    $out .= "~\n";

  ++$edicount;
  
  
  $out .= "PER" .
    "*IC" .
    "*" . $claim->billingContactName() .
    "*TE" .
    "*" . $claim->billingContactPhone();
  if (!$CMS_5010 && $claim->x12gsper06()) {
    $out .= "*ED*" . $claim->x12gsper06();
  }
  $out .= "~\n";

  ++$edicount;
  
  
  $out .= "NM1" .       // Loop 1000B Receiver stays in the 837I
    "*40" .
    "*2" .
    "*" . $claim->clearingHouseName() .
    "*" .
    "*" .
    "*" .
    "*" .
    "*46" .
    "*" . $claim->clearingHouseETIN() .
    "~\n";

  $HLcount = 1;

  ++$edicount;
  

  
  $out .= "HL" .        // Loop 2000A Billing/Pay-To Provider HL Loop
    "*$HLcount" .
    "*" .
    "*20" .
    "*1" .              // 1 indicates there are child segments
    "~\n";

  $HLBillingPayToProvider = $HLcount++;

  // Situational PRV segment (for provider taxonomy code) omitted here.
  // Situational CUR segment (foreign currency information) omitted here.

  ++$edicount;
  //Field length is limited to 35. See nucc dataset page 63 www.nucc.org
  $billingFacilityName = substr($claim->billingFacilityName(), 0, $CMS_5010 ? 60 : 35);

  $out .= "NM1" .       // Loop 2010AA Billing Provider stays in the 837I
    "*85" .
    "*2" .
    "*" . $billingFacilityName .
    "*" .
    "*" .
    "*" .
    "*";
  if ($claim->billingFacilityNPI()) {
    $out .= "*XX*" . $claim->billingFacilityNPI();
  }
  else {
    $log .= "*** Billing facility has no NPI.\n";
    if ($CMS_5010) {
      $out .= "*XX*";
    }
    else {
      $out .= "*24*" . $claim->billingFacilityETIN();
    }
  }
  $out .= "~\n";

  ++$edicount;
  $out .= "N3" .
    "*" . $claim->billingFacilityStreet() .
    "~\n";

  ++$edicount;
  $out .= "N4" .
    "*" . $claim->billingFacilityCity() .
    "*" . $claim->billingFacilityState() .
    "*" . stripZipCode($claim->billingFacilityZip()) .
    "~\n";

  if ($CMS_5010 || ($claim->billingFacilityNPI() && $claim->billingFacilityETIN())) {
    ++$edicount;
    $out .= "REF" ;
    if($claim->federalIdType()){
      $out .= "*" . $claim->federalIdType();
    }
    else{
      $out .= "*EI"; // For dealing with the situation before adding TaxId type In facility.
    }
    $out .=  "*" . $claim->billingFacilityETIN() .
      "~\n";
  }

  if ($claim->providerNumberType() && $claim->providerNumber() &&
      !($CMS_5010 && $claim->billingFacilityNPI()))
  {
    ++$edicount;
    $out .= "REF" .
      "*" . $claim->providerNumberType() .
      "*" . $claim->providerNumber() .
      "~\n";
  }
  else if ($claim->providerNumber() && !$claim->providerNumberType()) {
    $log .= "*** Payer-specific provider insurance number is present but has no type assigned.\n";
  }

  // Situational PER*1C segment.
  ++$edicount;
  $out .= "PER" .
    "*IC" .
    "*" . $claim->billingContactName() .
    "*TE" .
    "*" . $claim->billingContactPhone();
  if (!$CMS_5010 && $claim->x12gsper06()) {
    $out .= "*ED*" . $claim->x12gsper06();
  }
  $out .= "~\n";

  // Pay-To Address defaults to billing provider and is no longer required in 5010.
  if (!$CMS_5010) {
    ++$edicount;
    // Field length is limited to 35. See nucc dataset page 63 www.nucc.org
    $billingFacilityName = substr($claim->billingFacilityName(), 0, $CMS_5010 ? 60 : 35);
    $out .= "NM1" .       // Loop 2010AB Pay-To Provider
      "*87" .
      "*2" .
      "*" . $billingFacilityName .
      "*" .
      "*" .
      "*" .
      "*";
    if ($claim->billingFacilityNPI())
      $out .= "*XX*" . $claim->billingFacilityNPI();
    else
      $out .= "*24*" . $claim->billingFacilityETIN();
    $out .= "~\n";

    ++$edicount;
    $out .= "N3" .
      "*" . $claim->billingFacilityStreet() .
      "~\n";

    ++$edicount;
    $out .= "N4" .
      "*" . $claim->billingFacilityCity() .
      "*" . $claim->billingFacilityState() .
      "*" . stripZipCode($claim->billingFacilityZip()) .
      "~\n";

    if ($claim->billingFacilityNPI() && $claim->billingFacilityETIN()) {
      ++$edicount;
      $out .= "REF" .
        "*EI" .
        "*" . $claim->billingFacilityETIN() .
        "~\n";
    }
  }

  // This is also excluded in the 837I
  // Loop 2010AC Pay-To Plan Name omitted.  Includes:
  // NM1*PE, N3, N4, REF*2U, REF*EI

  $PatientHL = $claim->isSelfOfInsured() ? 0 : 1;
  $HLSubscriber = $HLcount++;

  ++$edicount;
  
  // loop 2000B 
  $out .= "HL" .        // Loop 2000B Subscriber HL Loop 
    "*$HLSubscriber" .
    "*$HLBillingPayToProvider" .
    "*22" .
    "*$PatientHL" .
    "~\n";

  if (!$claim->payerSequence()) {
    $log .= "*** Error: Insurance information is missing!\n";
  }

  ++$edicount;
  
  //SBR01 is either a P or S    SBR02 for care is always 18 "patient"  SBR09 is always MA
  $out .= "SBR" .       // Subscriber Information
    "*" . $claim->payerSequence() .
    "*" . ($claim->isSelfOfInsured() ? '18' : '') .
    "*" . $claim->groupNumber() .
    "*" . (($CMS_5010 && $claim->groupNumber()) ? '' : $claim->groupName()) .
    "*" . $claim->insuredTypeCode() . // applies for secondary medicare
    "*" .
    "*" .
    "*" .
    "*" . $claim->claimType() . // Zirmed replaces this
    "~\n";

  // 2000C Segment PAT omitted.

  ++$edicount;
  $out .= "NM1" .       // Loop 2010BA Subscriber  same in 837I
    "*IL" .
    "*1" . // 1 = person, 2 = non-person
    "*" . $claim->insuredLastName() .
    "*" . $claim->insuredFirstName() .
    "*" . $claim->insuredMiddleName() .
    "*" .
    "*" . // Name Suffix
    "*MI" .
    // "MI" = Member Identification Number
    // "II" = Standard Unique Health Identifier, "Required if the
    //        HIPAA Individual Patient Identifier is mandated use."
    //        Here we presume that is not true yet.
    "*" . $claim->policyNumber() .
    "~\n";

  // For 5010, further subscriber info is sent only if they are the patient.
  if (!$CMS_5010 || $claim->isSelfOfInsured()) {
    ++$edicount;
    $out .= "N3" .
      "*" . $claim->insuredStreet() .
      "~\n";

    ++$edicount;
    $out .= "N4" .
      "*" . $claim->insuredCity() .
      "*" . $claim->insuredState() .
      "*" . stripZipCode($claim->insuredZip()) .
      "~\n";

    ++$edicount;
    $out .= "DMG" .
      "*D8" .
      "*" . $claim->insuredDOB() .
      "*" . $claim->insuredSex() .
      "~\n";
  }

  // Segment REF*SY (Subscriber Secondary Identification) omitted.
  // Segment REF*Y4 (Property and Casualty Claim Number) omitted.
  // Segment PER*IC (Property and Casualty Subscriber Contact Information) omitted.

  ++$edicount;
  //Field length is limited to 35. See nucc dataset page 81 www.nucc.org
  $payerName = substr($claim->payerName(), 0, $CMS_5010 ? 60 : 35);
  $out .= "NM1" .       // Loop 2010BB Payer
    "*PR" .
    "*2" .
    "*" . $payerName .
    "*" .
    "*" .
    "*" .
    "*" .
    // The 5010 spec says:
    // "On or after the mandated implementation date for the HIPAA
    // National Plan Identifier (National Plan ID), XV must be sent.
    // Prior to the mandated implementation date and prior to any phase-
    // in period identified by Federal regulation, PI must be sent."
    // *************** Anybody know what that date is? ***************
	//    August 2011 - Publish interim final rule
//    October 1, 2012-March 31, 2013 - Enumeration
//    April 1, 2013-September 30, 2013 - Testing
 //   October 1, 2013 - Implementation

    "*PI" .
    // Zirmed ignores this if using payer name matching:
    "*" . ($encounter_claim ? $claim->payerAltID() : $claim->payerID()) .
    "~\n";

  // if (!$claim->payerID()) {
  //   $log .= "*** CMS ID is missing for payer '" . $claim->payerName() . "'.\n";
  // }

  if (true) { // !$CMS_5010
    // The 5010 spec says:
    // "Required when the payer address is available and the submitter intends
    // for the claim to be printed on paper at the next EDI location (for example, a
    // clearinghouse). If not required by this implementation guide, do not send."

    ++$edicount;
    $out .= "N3" .
      "*" . $claim->payerStreet() .
      "~\n";

    ++$edicount;
    $out .= "N4" .
      "*" . $claim->payerCity() .
      "*" . $claim->payerState() .
      "*" . stripZipCode($claim->payerZip()) .
      "~\n";
  }

  // Segment REF (Payer Secondary Identification) omitted.
  // Segment REF (Billing Provider Secondary Identification) omitted.

  
  
  if (! $claim->isSelfOfInsured()) {
    ++$edicount;
    $out .= "HL" .        // Loop 2000C Patient Information
      "*$HLcount" .
      "*$HLSubscriber" .
      "*23" .
      "*0" .
      "~\n";

    $HLcount++;

    ++$edicount;
    $out .= "PAT" .
      "*" . $claim->insuredRelationship() .
      "~\n";

    ++$edicount;
    $out .= "NM1" .       // Loop 2010CA Patient may need this elsed in to the loop 2000C
      "*QC" .
      "*1" .
      "*" . $claim->patientLastName() .
      "*" . $claim->patientFirstName();
    if ($claim->patientMiddleName() !== '') $out .= "*"
. $claim->patientMiddleName();
    $out .= "~\n";

    ++$edicount;
    $out .= "N3" .
      "*" . $claim->patientStreet() .
      "~\n";

    ++$edicount;
    $out .= "N4" .
      "*" . $claim->patientCity() .
      "*" . $claim->patientState() .
      "*" . stripZipCode($claim->patientZip()) .
      "~\n";

    ++$edicount;
    $out .= "DMG" .
      "*D8" .
      "*" . $claim->patientDOB() .
      "*" . $claim->patientSex() .
      "~\n";

    // Segment REF*Y4 (Property and Casualty Claim Number) omitted.
    // Segment REF (Property and Casualty Patient Identifier) omitted.
    // Segment PER (Property and Casualty Patient Contact Information) omitted.

  } // end of patient different from insured

  $proccount = $claim->procCount();

  $clm_total_charges = 0;
  for ($prockey = 0; $prockey < $proccount; ++$prockey) {
    $clm_total_charges += $claim->cptCharges($prockey);
  }

  if (!$clm_total_charges) {
    $log .= "*** This claim has no charges!\n";
  }

  ++$edicount;
  $out .= "CLM" .       // Loop 2300 Claim
    "*$pid-$encounter" .
    "*"  . sprintf("%.2f",$clm_total_charges) . // Zirmed computes and replaces this
    "*"  .
    "*"  ;
	//Service location this need to be bill type from ub form type_of_bill
    //IF (substr($claim->ub04_options[type_of_bill],0,1) == '0'AND strlen($claim->ub04_options[type_of_bill]) == 4){
		IF (strlen($claim->ub04_options[type_of_bill]) >= 3){
			    $out .= "*"  . substr($claim->ub04_options['type_of_bill'],1,1) . ":" .
                 substr($claim->ub04_options['type_of_bill'],2,1) . ":" .
                 substr($claim->ub04_options['type_of_bill'],3,1) ;
	}
 
	//"*"  . sprintf('%02d', $claim->facilityPOS()) . ":" .
    //       ($CMS_5010 ? "B" : "") . ":" .
    //       $claim->frequencyTypeCode() .    
    $out .= "*" .
    "*A" .
    "*"  . ($claim->billingFacilityAssignment() ? 'Y' : 'N') .
    "*Y" .
    ($CMS_5010 ? "" : "*C") .
    "~\n"; 
	

	
	// discharge hour
	// DTP 096 TM  (Discharge hour from form)
	
if ($claim->ub04_options['discharge_hour']) {
  ++$edicount;
  $out .= "DTP" .     // Loop 2300 
      "*096" .
      "*TM" .
      "*" . $claim->ub04_options['discharge_hour'] .
      "~\n";
} 
	
	// Statment Dates
	// DTP 434 RD8  (Statment from OR to date)

if ($claim->ub04_options['hospitalization_date_from']) {
  ++$edicount;
  $tmp = substr($claim->ub04_options['hospitalization_date_from'],0 ,4) . substr($claim->ub04_options['hospitalization_date_from'],5,2). substr($claim->ub04_options['hospitalization_date_from'],8,2);
  $tmp1 = substr($claim->ub04_options['hospitalization_date_to'],0 ,4) . substr($claim->ub04_options['hospitalization_date_to'],5,2). substr($claim->ub04_options['hospitalization_date_to'],8,2);
  $out .= "DTP" .     // Loop 2300 
      "*434" .
      "*RD8" .
      "*" . $tmp .'-'. $tmp1.
      "~\n";
} 	

  // if ($claim->onsetDate() && 
  //    ($claim->onsetDate()!== $claim->serviceDate()) &&
  //    ($claim->onsetDateValid()) 
  //  ) {
  //  ++$edicount;
  //  $out .= "DTP" .       // Date of Onset
  //    "*431" .
  //    "*D8" .
  //    "*" . $claim->onsetDate() .
  //    "~\n";
  //}

  //if ($claim->dateInitialTreatment() && ($claim->onsetDateValid())) {
  //  ++$edicount;
  //  $out .= "DTP" .       // Date of Initial Treatment
  //    "*454" .
  //    "*D8" .
  //    "*" . $claim->dateInitialTreatment() .
  //    "~\n";
  //}

  
  	// Admission Date / Hour
	// DTP 435 DT  (Admission Date and Hour from form)
	
if ($claim->ub04_options['hospitalization_date_from']) {
  ++$edicount;
  $tmp = substr($claim->ub04_options['hospitalization_date_from'],0 ,4) . substr($claim->ub04_options['hospitalization_date_from'],5,2). substr($claim->ub04_options['hospitalization_date_from'],8,2);
  $out .= "DTP" .     // Loop 2300 
      "*435" .
      "*DT" .
	  "*" . $tmp . $claim->ub04_options['admission_hour'] .
      "~\n";
} 

  if (strlen(trim($claim->ub04_options['admission_hour'])) == 0) {
    $log .= "*** Error: No Admission Time Entered!\n";
  }

 	// Repricer Received Date
	// DTP 050 D8  (Admission Date and Hour from form)
	
	
	
	// Institutional Claim Code
	// CL1 (Admission Type Code) (Admission Source Code)  (Patient Status Code)
	// This may not be needed for any thing except a hospital thats why it ic commited out
	
  if ($claim->ub04_options['is_hospitalized']) {
    ++$edicount;
    $out .= "CL1" .     // Loop 2300 
      "*" . $claim->ub04_options['admit_type'] .
      "*" . $claim->ub04_options['admit_source'] .
 	  "*" . $claim->ub04_options['discharge_status'] .
      "~\n";
 } 	
  
  // Segment PWK (Claim Supplemental Information) omitted.
  
  
  // Segment CN1 (Contract Information) omitted.

  
  // Patient Estimated Amount Due
  // Check logic
  
//  $patientpaid = $claim->patientPaidAmount();
//  if ($patientpaid != 0) {
//    ++$edicount;
//    $out .= "AMT" .     // Patient paid amount. Page 190/220.
//      "*F5" .
//      "*" . $patientpaid .
//      "~\n";
//  }

  // Segment REF*4N (Service Authorization Exception Code) omitted.
  // Segment REF*9F (Referral Number) omitted.

  // Prior Authorization
  //
  if ($claim->priorAuth()) {
    ++$edicount;
    $out .= "REF" .     // Prior Authorization Number
      "*G1" .
      "*" . $claim->priorAuth() .
      "~\n";
  }

  // Segment REF*F8 (Payer Claim Control Number) omitted.
  // This may be needed for the UB04 Claim if so change the 'MB' to 'MA'
  //if ($claim->cliaCode() && ($CMS_5010 || $claim->claimType() === 'MB')) {
    // Required by Medicare when in-house labs are done.
  //  ++$edicount;
  //  $out .= "REF" .     // Clinical Laboratory Improvement Amendment Number
  //    "*X4" .
  //    "*" . $claim->cliaCode() .
  //    "~\n";
 // }

  // Segment REF*9A (Repriced Claim Number) omitted.
  // Segment REF*9C (Adjusted Repriced Claim Number) omitted.
  // Segment REF*LX (Investigational Device Exemption Number) omitted.
  // Segment REF*S9 (Claim Identifier for Transmission Intermediaries) omitted.
  // Segment REF*LU (Auto Accident State) omitted.
  // Segment REF*EA (Medical Record Number) omitted.
  // Segment REF*P4 (Demonstration Project Identifier) omitted.
  // Segment REF*G4 (Peer Review Organization PRO Approval Number) omitted.
  // Segment K3 (File Information) omitted.

  if ($claim->additionalNotes()) {
    
	// Claim Note
	// Has a list of valaid codes. Required when PROVIDER deems necessary
	
	// Billing note.
	// Check to verify I am getting this information on the ub04 form
	
    ++$edicount;
    $out .= "NTE" .     // comments box 19
      "*" . ($CMS_5010 ? "ADD" : "") .
      "*" . $claim->additionalNotes() .
      "~\n";
  }


  // Segment CRC (EPSDT Referral) omitted.

  // Diagnoses, up to $max_per_seg per HI segment.  Check this 
  $max_per_seg = 18 ;
  $da = $claim->diagArray();
  if ($claim->diagtype == "ICD9") {
  $diag_type_code = 'BK';
  }
  ELSE
  {
  $diag_type_code = 'ABK';
  }
  $tmp = 0;
  foreach ($da as $diag) {
	if ($tmp == 1) continue;
    if ($tmp % $max_per_seg == 0) {
      if ($tmp) $out .= "~\n";
      ++$edicount;
      $out .= "HI";         // Health Diagnosis Codes
    }
    $out .= "*$diag_type_code:" . $diag;
      if ($claim->diagtype == "ICD9") {
         $diag_type_code = 'BF';
      }
      ELSE
      {
      $diag_type_code = 'ABF';
      }
	
    ++$tmp;
  }
  if ($tmp) $out .= "~\n";

  // Segment HI*BI (Occurrence Span Information).
  // HI BI (Occurrence Span Code 1) RD8 (Occurrence Span Code Associated Date)
  if ($claim->ub04_options['occurrence_span_code_1']) {
  
  $max_per_seg = 4 ;
  $diag_type_code = 'BI';
 
  $tmp = 0;
  
  for ($i=0; $i<=3;) {
    if ($tmp % $max_per_seg == 0) {
      if ($tmp) $out .= "~\n";
      ++$edicount;
      $out .= "HI";         // Health Diagnosis Codes
    }
	if ($claim->ub04_options['occurrence_span_code_1']) {
	//if ($i=1) {
	  $out .= "*$diag_type_code:" . $claim->ub04_options['occurrence_span_code_1'] . 
	  ":" . $claim->ub04_options['occurrence_span_date_from_1'] .
	  ":" . $claim->ub04_options['occurrence_span_date_to_1'] ;
        $diag_type_code = 'BI';
    //}
	}
    if ($i=2) {
	if ($claim->ub04_options['occurrence_span_code_2']) {
	  $out .= "*$diag_type_code:" . $claim->ub04_options['occurrence_span_code_2'] . 
	  ":" . $claim->ub04_options['occurrence_span_date_from_2'] .
	  ":" . $claim->ub04_options['occurrence_span_date_to_2'] ;
        $diag_type_code = 'BI';
    }
	}
    if ($i=3) {
    if ($claim->ub04_options['occurrence_span_code_1A']) {
	$out .= "*$diag_type_code:" . $claim->ub04_options['occurrence_span_code_1A'] . 
	  ":" . $claim->ub04_options['occurrence_span_date_from_1A'] .
	  ":" . $claim->ub04_options['occurrence_span_date_to_1A'] ;
        $diag_type_code = 'BI';
    }
	}
	if ($i=4) {
	if ($claim->ub04_options['occurrence_span_code_2A']) {
	
	  $out .= "*$diag_type_code:" . $claim->ub04_options['occurrence_span_code_2A'] . 
	  ":" . $claim->ub04_options['occurrence_span_date_from_2A'] .
	  ":" . $claim->ub04_options['occurrence_span_date_to_2A'] ;
        $diag_type_code = 'BI';
    }
	}
    ++$tmp;
	++$i ;
  }
  if ($tmp) $out .= "~\n";

}


  
  // Segment HI*BH (Occurrence Information).
  // HI BH (Occurrence Code 1) D8 (Occurrence Code Associated Date)
 
 if ($claim->ub04_options['occurrence_code_1']) {
  
  $max_per_seg = 8 ;
  $diag_type_code = 'BH';
 
  $tmp = 0;
  
  for ($i=0; $i<=7;) {
    if ($tmp % $max_per_seg == 0) {
      if ($tmp) $out .= "~\n";
      ++$edicount;
      $out .= "HI";         // Health Diagnosis Codes
    }
	
		if ($claim->ub04_options['occurrence_code_1']) {
	//if ($i=1) {
	  $out .= "*$diag_type_code:" . $claim->ub04_options['occurrence_code_1'] . 
	  ":D8" .
	  ":" . $claim->ub04_options['occurrence_date_1'] ;
        $diag_type_code = 'BH';
    //}
	}
    if ($i=2) {
	if ($claim->ub04_options['occurrence_code_2']) {
	  $out .= "*$diag_type_code:" . $claim->ub04_options['occurrence_code_2'] . 
	  ":D8" .
	  ":" . $claim->ub04_options['occurrence_date_2'] ;
        $diag_type_code = 'BH';
    }
	}
    if ($i=3) {
    if ($claim->ub04_options['occurrence_code_3']) {
	$out .= "*$diag_type_code:" . $claim->ub04_options['occurrence_code_3'] . 
	  ":D8" .
	  ":" . $claim->ub04_options['occurrence_date_3'] ;
        $diag_type_code = 'BH';
    }
	}
	if ($i=4) {
	if ($claim->ub04_options['occurrence_code_4']) {
	  $out .= "*$diag_type_code:" . $claim->ub04_options['occurrence_code_4'] . 
	  ":D8" .
	  ":" . $claim->ub04_options['occurrence_date_4'] ;
        $diag_type_code = 'BH';
    }
	}
	
	if ($claim->ub04_options['occurrence_code_1A']) {
	if ($i=5) {
	  $out .= "*$diag_type_code:" . $claim->ub04_options['occurrence_code_1A'] . 
	  ":D8" .
	  ":" . $claim->ub04_options['occurrence_date_1A'] ;
        $diag_type_code = 'BH';
    }
	}
    if ($i=6) {
	if ($claim->ub04_options['occurrence_code_2A']) {
	  $out .= "*$diag_type_code:" . $claim->ub04_options['occurrence_code_2A'] . 
	  ":D8" .
	  ":" . $claim->ub04_options['occurrence_date_2A'] ;
        $diag_type_code = 'BH';
    }
	}
    if ($i=7) {
    if ($claim->ub04_options['occurrence_code_3A']) {
	$out .= "*$diag_type_code:" . $claim->ub04_options['occurrence_code_3A'] . 
	  ":D8" .
	  ":" . $claim->ub04_options['occurrence_date_3A'] ;
        $diag_type_code = 'BH';
    }
	}
	if ($i=8) {
	if ($claim->ub04_options['occurrence_code_4A']) {
	  $out .= "*$diag_type_code:" . $claim->ub04_options['occurrence_code_4A'] . 
	  ":D8" .
	  ":" . $claim->ub04_options['occurrence_date_4A'] ;
        $diag_type_code = 'BH';
    }
	}
    ++$tmp;
	++$i ;
  }
  if ($tmp) $out .= "~\n";

}


  // Segment HI*BE (Value Information).
  // HI BE (Value Code 1) *.* (Value Code Amount)
 
if ($claim->ub04_options['value_code_1a']) {
  
  $max_per_seg = 12 ;
  $diag_type_code = 'BE';
 
  $tmp = 0;
  
  for ($i=0; $i<=11;) {
    if ($tmp % $max_per_seg == 0) {
      if ($tmp) $out .= "~\n";
      ++$edicount;
      $out .= "HI";         // Health Diagnosis Codes
    }
	
		if ($claim->ub04_options['value_code_1a']) {
	//if ($i=1) {
	  $out .= "*$diag_type_code:" . $claim->ub04_options['value_code_1a'] . 
	  ":" .
	  ":" . $claim->ub04_options['value_code_amount_1a'] ;
        $diag_type_code = 'BE';
    //}
	}
    if ($i=2) {
	if ($claim->ub04_options['value_code_2a']) {
	  $out .= "*$diag_type_code:" . $claim->ub04_options['value_code_2a'] . 
	  ":" .
	  ":" . $claim->ub04_options['value_code_amount_2a'] ;
        $diag_type_code = 'BE';
    }
	}
    if ($i=3) {
    if ($claim->ub04_options['value_code_3a']) {
	$out .= "*$diag_type_code:" . $claim->ub04_options['value_code_3a'] . 
	  ":" .
	  ":" . $claim->ub04_options['value_code_amount_3a'] ;
        $diag_type_code = 'BE';
    }
	}
	if ($i=4) {
	if ($claim->ub04_options['value_code_1b']) {
	  $out .= "*$diag_type_code:" . $claim->ub04_options['value_code_1b'] . 
	  ":" .
	  ":" . $claim->ub04_options['value_code_amount_1b'] ;
        $diag_type_code = 'BE';
    }
	}
	if ($i=5) {
	if ($claim->ub04_options['value_code_2b']) {
	  $out .= "*$diag_type_code:" . $claim->ub04_options['value_code_2b'] . 
	  ":" .
	  ":" . $claim->ub04_options['value_code_amount_2b'] ;
        $diag_type_code = 'BE';
    }
	}
    if ($i=6) {
	if ($claim->ub04_options['value_code_3b']) {
	  $out .= "*$diag_type_code:" . $claim->ub04_options['value_code_3b'] . 
	  ":" .
	  ":" . $claim->ub04_options['value_code_amount_3b'] ;
        $diag_type_code = 'BE';
    }
	}
    if ($i=7) {
    if ($claim->ub04_options['value_code_1c']) {
	$out .= "*$diag_type_code:" . $claim->ub04_options['value_code_1c'] . 
	  "*" .
	  ":" . $claim->ub04_options['value_code_amount_1c'] ;
        $diag_type_code = 'BE';
    }
	}
	if ($i=8) {
	if ($claim->ub04_options['value_code_2c']) {
	  $out .= "*$diag_type_code:" . $claim->ub04_options['value_code_2c'] . 
	  "*" .
	  ":" . $claim->ub04_options['value_code_amount_2c'] ;
        $diag_type_code = 'BE';
    }
	}
	if ($i=9) {
	if ($claim->ub04_options['value_code_3c']) {
	  $out .= "*$diag_type_code:" . $claim->ub04_options['value_code_3c'] . 
	  "*" .
	  ":" . $claim->ub04_options['value_code_amount_3c'] ;
        $diag_type_code = 'BE';
    }
	}
    if ($i=10) {
    if ($claim->ub04_options['value_code_1d']) {
	  $out .= "*$diag_type_code:" . $claim->ub04_options['value_code_1d'] . 
	  "*" .
	  ":" . $claim->ub04_options['value_code_amount_1d'] ;
        $diag_type_code = 'BE';
    }
	}
	if ($i=11) {
	if ($claim->ub04_options['value_code_2d']) {
	  $out .= "*$diag_type_code:" . $claim->ub04_options['value_code_2d'] . 
	  "*" .
	  ":" . $claim->ub04_options['value_code_amount_2d'] ;
        $diag_type_code = 'BE';
    }
	}
	if ($i=12) {
	if ($claim->ub04_options['value_code_3d']) {
	  $out .= "*$diag_type_code:" . $claim->ub04_options['value_code_3d'] . 
	  "*" .
	  ":" . $claim->ub04_options['value_code_amount_3d'] ;
        $diag_type_code = 'BE';
    }
	}
    ++$tmp;
	++$i ;
  }
  if ($tmp) $out .= "~\n";

}
 
  
  // Segment HI*BG (Condition Information).
  // HI BG (Condition Code 1)  

  if ($claim->ub04_options['condition_code_1']) {
  
  $max_per_seg = 11 ;
  $diag_type_code = 'BG';
 
  $tmp = 0;
  
  for ($i=0; $i<=10;) {
    if ($tmp % $max_per_seg == 0) {
      if ($tmp) $out .= "~\n";
      ++$edicount;
      $out .= "HI";         // Health Diagnosis Codes
    }
	
		if ($claim->ub04_options['condition_code_1']) {
	//if ($i=1) {
	  $out .= "*$diag_type_code:" . $claim->ub04_options['condition_code_1'] ;
        $diag_type_code = 'BG';
    //}
	}
    if ($i=2) {
	if ($claim->ub04_options['condition_code_2']) {
	    $out .= "*$diag_type_code:" . $claim->ub04_options['condition_code_2'];
        $diag_type_code = 'BG';
    }
	}
    if ($i=3) {
    if ($claim->ub04_options['condition_code_3']) {
	    $out .= "*$diag_type_code:" . $claim->ub04_options['condition_code_3'] ;
        $diag_type_code = 'BG';
    }
	}
	if ($i=4) {
	if ($claim->ub04_options['condition_code_4']) {
	    $out .= "*$diag_type_code:" . $claim->ub04_options['condition_code_4'] ;
        $diag_type_code = 'BG';
    }
	}
    if ($i=5) {
	if ($claim->ub04_options['condition_code_5']) {
	    $out .= "*$diag_type_code:" . $claim->ub04_options['condition_code_5'];
        $diag_type_code = 'BG';
    }
	}
    if ($i=6) {
    if ($claim->ub04_options['condition_code_6']) {
	    $out .= "*$diag_type_code:" . $claim->ub04_options['condition_code_6'] ;
        $diag_type_code = 'BG';
    }
	}
    if ($i=7) {  	
	if ($claim->ub04_options['condition_code_7']) {
	    $out .= "*$diag_type_code:" . $claim->ub04_options['condition_code_7'] ;
        $diag_type_code = 'BG';
    }
	}
    if ($i=8) {
	if ($claim->ub04_options['condition_code_8']) {
	    $out .= "*$diag_type_code:" . $claim->ub04_options['condition_code_8'];
        $diag_type_code = 'BG';
    }
	}
    if ($i=9) {
    if ($claim->ub04_options['condition_code_9']) {
	    $out .= "*$diag_type_code:" . $claim->ub04_options['condition_code_9'] ;
        $diag_type_code = 'BG';
    }
	}
	if ($i=10) {
	if ($claim->ub04_options['condition_code_10']) {
	
	  $out .= "*$diag_type_code:" . $claim->ub04_options['condition_code_10'] ;
        $diag_type_code = 'BG';
    }
	}
    if ($i=11) {
	if ($claim->ub04_options['condition_code_11']) {
	  $out .= "*$diag_type_code:" . $claim->ub04_options['condition_code_11'];
        $diag_type_code = 'BG';
    }
	}

    ++$tmp;
	++$i ;
  }
  if ($tmp) $out .= "~\n";

}
  

  
  // Segment HI*TC (Treatment Code Information).
  // HI TC (Treatment Code 1)  
  
   if ($claim->ub04_options['prior_auth_number']) {
  
  $max_per_seg = 3 ;
  $diag_type_code = 'TC';
 
  $tmp = 0;
  
  for ($i=0; $i<=2;) {
    if ($tmp % $max_per_seg == 0) {
      if ($tmp) $out .= "~\n";
      ++$edicount;
      $out .= "HI";         // Health Diagnosis Codes
    }
	
		if ($claim->ub04_options['prior_auth_number']) {
	//if ($i=1) {
	  $out .= "*$diag_type_code:" . $claim->ub04_options['prior_auth_number'] ;
        $diag_type_code = 'TC';
    //}
	}
    if ($i=2) {
	if ($claim->ub04_options['prior_auth_number_1']) {
	    $out .= "*$diag_type_code:" . $claim->ub04_options['prior_auth_number_1'];
        $diag_type_code = 'TC';
    }
	}
    if ($i=3) {
    if ($claim->ub04_options['prior_auth_number_2']) {
	    $out .= "*$diag_type_code:" . $claim->ub04_options['prior_auth_number_2'] ;
        $diag_type_code = 'TC';
    }
	}


    ++$tmp;
	++$i ;
  }
  if ($tmp) $out .= "~\n";

}
  
  
  // Segment HCP (Claim Pricing/Repricing Information) omitted.
  
  
	// This needs to allow Attending Physician 2310A, Operating Physician Name 2310B, Other Operating Physician Name 2310C 
	// and Rendering Provider Name (Rendering Provider Name is futher down)
   

       if ($claim->ub04_options['attending_last_name']) {
    
    ++$edicount;
	

	
    $out .= "NM1" .     // Loop 2310A Attending Physician
      "*71" .
      "*1" .
      "*" . $claim->ub04_options['attending_last_name'] .
      "*" . $claim->ub04_options['attending_first_name'] .
      "*" . $claim->ub04_options['attending_middl_name'] .  // middle name
      "*" .
      "*" ; 
     if ($claim->ub04_options['attending_qualifier'] == 'XX') { $out .=
        "*" . $claim->ub04_options['attending_qualifier'] .
        "*" . $claim->ub04_options['attending_npi'];
    } else { $out .=
        "*" .                            
        "*" ;
		$log .= "*** Attending Physician has no NPI.\n";
    }
	$out .= "~\n";
	

    //if (!$CMS_5010 && $claim->referrerTaxonomy()) {
    //  ++$edicount;
    //  $out .= "PRV" .
    //    "*RF" . // Attending Physician
    //    "*ZZ" .
    //    "*" . $claim->referrerTaxonomy() .
    //    "~\n";
   // }

    if ($claim->ub04_options['attending_qualifier'] == '1G') {
      ++$edicount;
      $out .= "REF" .   // Attending Physician Secondary Identification
        "*" . $claim->ub04_options['attending_qualifier'] .
        "*" . $claim->ub04_options['attending_upin'] .
        "~\n";
    }
  }

 
  //2310B
  
       if ($claim->ub04_options['operating_last_name']) {
    
    ++$edicount;
		
    $out .= "NM1" .     // Loop 2310B  operating Physician
      "*72" .
      "*1" .
      "*" . $claim->ub04_options['operating_last_name'] .
      "*" . $claim->ub04_options['operating_first_name'] .
      "*" . $claim->ub04_options['operating_middl_name'] . // middle name
      "*" .
      "*" ;    
    if ($claim->ub04_options['operating_qualifier'] == 'XX') { $out .=
        "*" . $claim->ub04_options['operating_qualifier'] .
        "*" . $claim->ub04_options['operating_npi'];
    } else { $out .=
        "*" .                            
        "*" ;
		$log .= "*** Operating Physician has no NPI.\n";
    }
	$out .= "~\n"; 

    //if (!$CMS_5010 && $claim->referrerTaxonomy()) {
    //  ++$edicount;
    //  $out .= "PRV" .
    //    "*RF" . // other operating Physician
    //    "*ZZ" .
    //    "*" . $claim->referrerTaxonomy() .
    //    "~\n";
   // }

    if ($claim->ub04_options[operating_qualifier] == '1G') {
      ++$edicount;
      $out .= "REF" .   // operating Physician Secondary Identification
        "*" . $claim->ub04_options['operating_qualifier'] .
        "*" . $claim->ub04_options['operating_upin'] .
        "~\n";
    }
  }
  
  
  //2310C
  
       if ($claim->ub04_options['other_2_last_name']) {
    
    ++$edicount;
	
    $out .= "NM1" .     // Loop 2310C other operating Physician
      "*73" .
      "*1" .
      "*" . $claim->ub04_options['other_2_last_name'] .
      "*" . $claim->ub04_options['other_2_first_name'] .
      "*" . $claim->ub04_options['other_2_middl_name'] .  // middle name
      "*" .
      "*" ;    
   if ($claim->ub04_options['other_2_qualifier'] == 'XX') { $out .=
        "*" . $claim->ub04_options['other_2_qualifier'] .
        "*" . $claim->ub04_options['other_2_npi'];
    } else { $out .=
        "*" .                            
        "*" ;
		$log .= "*** Other Operating Physician has no NPI.\n";
    }
	$out .= "~\n"; 

    //if (!$CMS_5010 && $claim->referrerTaxonomy()) {
    //  ++$edicount;
    //  $out .= "PRV" .
    //    "*RF" . // other operating Physician
    //    "*ZZ" .
    //    "*" . $claim->referrerTaxonomy() .
    //    "~\n";
   // }

    if ($claim->ub04_options['other_2_qualifier'] == '1G') {
      ++$edicount;
      $out .= "REF" .   // other operating Physician Secondary Identification
        "*" . $claim->ub04_options['other_2_qualifier'] .
        "*" . $claim->ub04_options['other_2_upin'] .
        "~\n";
    }
  }

  
  /* Per the implementation guide lines, only include this information if it is different 
   * than the Loop 2010AA information
   */
  if(!$CMS_5010 || 
          ($claim->providerNPIValid() && 
          $claim->billingFacilityNPI() !== $claim->providerNPI() ))
  {
    ++$edicount;
    $out .= "NM1" .       // Loop 2310D Rendering Provider
        "*82" .
        "*1" .
        "*" . $claim->providerLastName() .
        "*" . $claim->providerFirstName() .
        "*" . $claim->providerMiddleName() .
        "*" .
        "*";
    if ($CMS_5010 || $claim->providerNPI()) { $out .=
        "*XX" .
        "*" . $claim->providerNPI();
    } else { $out .=
        "*34" .                             // not allowed for 5010
        "*" . $claim->providerSSN();
        $log .= "*** Rendering provider has no NPI.\n";
    }
    $out .= "~\n";

//    if ($claim->providerTaxonomy()) {
//        ++$edicount;
//        $out .= "PRV" .
//        "*PE" . // PErforming provider
//        "*" . ($CMS_5010 ? "PXC" : "ZZ") .
//        "*" . $claim->providerTaxonomy() .
//        "~\n";
//    }
    // End of Loop 2310D
  }
  else
  {
    // This loop can only get skipped if we are generating a 5010 claim
    if(!($claim->providerNPIValid()))
    {
        /* If the loop was skipped because the provider NPI was invalid, generate
         * a warning for the log.*/
        $log.="*** Skipping 2310B because ".$claim->providerLastName() ."," . $claim->providerFirstName() . " has invalid NPI.\n";
    }
    /* Skipping this segment because the providerNPI and the billingFacilityNPI are identical
     * is a normal condition, so no need to warn.
     */
    
  }
  
  // 4010: REF*1C is required here for the Medicare provider number if NPI was
  // specified in NM109.  Not sure if other payers require anything here.
  // --- apparently ECLAIMS, INC wants the data in 2010 but NOT in 2310B - tony@mi-squared.com
  //
  // 5010 spec says nothing here if NPI was specified.
  //
  if (($CMS_5010 && !$claim->providerNPI() && in_array($claim->providerNumberType(), array('0B','1G','G2','LU')))
      || (!$CMS_5010 && trim($claim->x12gsreceiverid()) != '470819582')) // if NOT ECLAIMS EDI
  {
    if ($claim->providerNumber()) {
      ++$edicount;
      $out .= "REF" .
        "*" . $claim->providerNumberType() .
        "*" . $claim->providerNumber() .
        "~\n";
    }
  }

  // Loop 2310D is omitted in the case of home visits (POS=12).
  if ($claim->facilityPOS() != 12 &&
      (!$CMS_5010 || $claim->facilityNPI() != $claim->billingFacilityNPI()))
    {
    ++$edicount;
	
	// Service Facility Name
	
    $out .= "NM1" .       // Loop 2310E Service Location
      "*77" .
      "*2";
    //Field length is limited to 35. See nucc dataset page 77 www.nucc.org
    $facilityName = substr($claim->facilityName(), 0, $CMS_5010 ? 60 : 35);
    if ($claim->facilityName() || $claim->facilityNPI() || $claim->facilityETIN()) { $out .=
      "*" . $facilityName;
    }
    if ($claim->facilityNPI() || $claim->facilityETIN()) { $out .=
      "*" .
      "*" .
      "*" .
      "*";
      if ($CMS_5010 || $claim->facilityNPI()) { $out .=
        "*XX*" . $claim->facilityNPI();
      } else { $out .=
        "*24*" . $claim->facilityETIN();
      }
      if (!$claim->facilityNPI()) {
        $log .= "*** Service location has no NPI.\n";
      }
    }
    $out .= "~\n";
    if ($claim->facilityStreet()) {
      ++$edicount;
      $out .= "N3" .
        "*" . $claim->facilityStreet() .
        "~\n";
    }
    if ($claim->facilityState()) {
      ++$edicount;
      $out .= "N4" .
        "*" . $claim->facilityCity() .
        "*" . $claim->facilityState() .
        "*" . stripZipCode($claim->facilityZip()) .
        "~\n";
    }
  }

  // Segment REF (Service Facility Location Secondary Identification) omitted.
  // Segment PER (Service Facility Contact Information) omitted.

  // Loop 2310F Referring Provider 
  
    if ($claim->referrerLastName()) {
    // Medicare requires referring provider's name and UPIN.
    ++$edicount;
	

	
    $out .= "NM1" .     // Loop 2310F Referring Provider this needs to change position
      "*DN" .
      "*1" .
      "*" . $claim->referrerLastName() .
      "*" . $claim->referrerFirstName() .
      "*" . $claim->referrerMiddleName() .
      "*" .
      "*";
    if ($CMS_5010 || $claim->referrerNPI()) { $out .=
      "*XX" .
      "*" . $claim->referrerNPI();
    } else { $out .=
      "*34" .                           // not allowed for 5010
      "*" . $claim->referrerSSN();
    }
    $out .= "~\n";

    if (!$CMS_5010 && $claim->referrerTaxonomy()) {
      ++$edicount;
      $out .= "PRV" .
        "*RF" . // ReFerring provider
        "*ZZ" .
        "*" . $claim->referrerTaxonomy() .
        "~\n";
    }

    if (!CMS_5010 && $claim->referrerUPIN()) {
      ++$edicount;
      $out .= "REF" .   // Referring Provider Secondary Identification
        "*1G" .
        "*" . $claim->referrerUPIN() .
        "~\n";
    }
  }
  
  // Loop 2310E, Supervising Provider
  //
  //if ($claim->supervisorLastName()) {
  //  ++$edicount;
  //  $out .= "NM1" .
  //    "*DQ" . // Supervising Physician
  //    "*1" .  // Person
  //    "*" . $claim->supervisorLastName() .
  //    "*" . $claim->supervisorFirstName() .
  //    "*" . $claim->supervisorMiddleName() .
  //    "*" .   // NM106 not used
  //    "*";    // Name Suffix
  //  if ($CMS_5010 || $claim->supervisorNPI()) { $out .=
  //    "*XX" .
  //    "*" . $claim->supervisorNPI();
  //  } else { $out .=
  //   "*34" .
  //    "*" . $claim->supervisorSSN();
  //  }
  //  if (!$claim->supervisorNPI()) {
  //    $log .= "*** Supervising Provider has no NPI.\n";
  //  }
  //  $out .= "~\n";

  //  if ($claim->supervisorNumber()) {
  //    ++$edicount;
  //    $out .= "REF" .
  //      "*" . $claim->supervisorNumberType() .
  //      "*" . $claim->supervisorNumber() .
  //      "~\n";
  //  }
  //}

  // Segments NM1*PW, N3, N4 (Ambulance Pick-Up Location) omitted.
  // Segments NM1*45, N3, N4 (Ambulance Drop-Off Location) omitted.

  $prev_pt_resp = $clm_total_charges; // for computation below

  // Loops 2320 and 2330*, other subscriber/payer information.
  // Remember that insurance index 0 is always for the payer being billed
  // by this claim, and 1 and above are always for the "other" payers.
  //
  for ($ins = 1; $ins < $claim->payerCount(); ++$ins) {

    $tmp1 = $claim->claimType($ins);
    $tmp2 = 'C1'; // Here a kludge. See page 321.
    if ($tmp1 === 'CI') $tmp2 = 'C1';
    if ($tmp1 === 'AM') $tmp2 = 'AP';
    if ($tmp1 === 'HM') $tmp2 = 'HM';
    if ($tmp1 === 'MB') $tmp2 = 'MB';
    if ($tmp1 === 'MA') $tmp2 = 'MA';
    if ($tmp1 === 'MC') $tmp2 = 'MC';
    if ($tmp1 === '09') $tmp2 = 'PP';
    ++$edicount;
	
	// This looks ok for 837I
	//
	
    $out .= "SBR" . // Loop 2320, Subscriber Information - page 297/318
      "*" . $claim->payerSequence($ins) .
      "*" . $claim->insuredRelationship($ins) .
      "*" . $claim->groupNumber($ins) .
      "*" . (($CMS_5010 && $claim->groupNumber($ins)) ? '' : $claim->groupName($ins)) .
      "*" . ($CMS_5010 ? $claim->insuredTypeCode($ins) : $tmp2) .
      "*" .
      "*" .
      "*" .
      "*" . $claim->claimType($ins) .
      "~\n";

    // Things that apply only to previous payers, not future payers.
    //
    if ($claim->payerSequence($ins) < $claim->payerSequence()) {

      // Generate claim-level adjustments.
      $aarr = $claim->payerAdjustments($ins);
      foreach ($aarr as $a) {
        ++$edicount;
        $out .= "CAS" . // Previous payer's claim-level adjustments. Page 301/323.
          "*" . $a[1] .
          "*" . $a[2] .
          "*" . $a[3] .
          "~\n";
      }

      $payerpaid = $claim->payerTotals($ins);
      ++$edicount;
      $out .= "AMT" . // Previous payer's paid amount. Page 307/332.
        "*D" .
        "*" . $payerpaid[1] .
        "~\n";

      // Segment AMT*A8 (COB Total Non-Covered Amount) omitted.
      // Segment AMT*EAF (Remaining Patient Liability) omitted.

// not 5010

      if (!$CMS_5010) {
        // Patient responsibility amount as of this previous payer.
        $prev_pt_resp -= $payerpaid[1]; // reduce by payments
        $prev_pt_resp -= $payerpaid[2]; // reduce by adjustments

        ++$edicount;
        $out .= "AMT" . // Allowed amount per previous payer. Page 334.
          "*B6" .
          "*" . sprintf('%.2f', $payerpaid[1] + $prev_pt_resp) .
          "~\n";

        ++$edicount;
        $out .= "AMT" . // Patient responsibility amount per previous payer. Page 335.
          "*F2" .
          "*" . sprintf('%.2f', $prev_pt_resp) .
          "~\n";
      }
    } // End of things that apply only to previous payers.

    if (!$CMS_5010) {
      ++$edicount;
      $out .= "DMG" . // Other subscriber demographic information. Page 342.
        "*D8" .
        "*" . $claim->insuredDOB($ins) .
        "*" . $claim->insuredSex($ins) .
        "~\n";
    }
	
// not 5010 ^

    ++$edicount;
    $out .= "OI" .  // Other Insurance Coverage Information. Page 310/344.
      "*" .
      "*" .
      "*" . ($claim->billingFacilityAssignment($ins) ? 'Y' : 'N') .
      // For this next item, the 5010 example in the spec does not match its
      // description.  So this might be wrong.
      "*" . ($CMS_5010 ? '' : 'B') .
      "*" .
      "*Y" .
      "~\n";

    // Segment MOA (Medicare Outpatient Adjudication) omitted.

    ++$edicount;
	
	// ok for 837I
	//
    $out .= "NM1" . // Loop 2330A Subscriber info for other insco. Page 315/350.
      "*IL" .
      "*1" .
      "*" . $claim->insuredLastName($ins) .
      "*" . $claim->insuredFirstName($ins) .
      "*" . $claim->insuredMiddleName($ins) .
      "*" .
      "*" .
      "*MI" .
      "*" . $claim->policyNumber($ins) .
      "~\n";

    ++$edicount;
    $out .= "N3" .
      "*" . $claim->insuredStreet($ins) .
      "~\n";

    ++$edicount;
    $out .= "N4" .
      "*" . $claim->insuredCity($ins) .
      "*" . $claim->insuredState($ins) .
      "*" . stripZipCode($claim->insuredZip($ins)) .
      "~\n";

    // Segment REF (Other Subscriber Secondary Identification) omitted.

    ++$edicount;
	
	// ok for 837I
	//
	
    //Field length is limited to 35. See nucc dataset page 81 www.nucc.org
    $payerName = substr($claim->payerName($ins), 0, $CMS_5010 ? 60 : 35);
    $out .= "NM1" . // Loop 2330B Payer info for other insco. Page 322/359.
      "*PR" .
      "*2" .
      "*" . $payerName .
      "*" .
      "*" .
      "*" .
      "*" .
      "*PI" .
      "*" . $claim->payerID($ins) .
      "~\n";

    // if (!$claim->payerID($ins)) {
    //   $log .= "*** CMS ID is missing for payer '" . $claim->payerName($ins) . "'.\n";
    // }

    // Payer address (N3 and N4) are added below so that Gateway EDI can
    // auto-generate secondary claims.  These do NOT appear in my copy of
    // the spec!  -- Rod 2008-06-12

    if ($CMS_5010 || trim($claim->x12gsreceiverid()) == '431420764') { // if Gateway EDI
      ++$edicount;
      $out .= "N3" .
        "*" . $claim->payerStreet($ins) .
        "~\n";
      //
      ++$edicount;
      $out .= "N4" .
        "*" . $claim->payerCity($ins) .
        "*" . $claim->payerState($ins) .
        "*" . stripZipCode($claim->payerZip($ins)) .
        "~\n";
    } // end Gateway EDI

    // Segment DTP*573 (Claim Check or Remittance Date) omitted.
    // Segment REF (Other Payer Secondary Identifier) omitted.
    // Segment REF*G1 (Other Payer Prior Authorization Number) omitted.
    // Segment REF*9F (Other Payer Referral Number) omitted.
    // Segment REF*T4 (Other Payer Claim Adjustment Indicator) omitted.
    // Segment REF*F8 (Other Payer Claim Control Number) omitted.
	// 2330C-I loops Omitted
 
  } // End loops 2320/2330*.

  $loopcount = 0;

  // Procedure loop starts here.
  //
  
  for ($tlh =0;$tlh< $proccount; ++$tlh) {
 	    $tmp = $claim->procs[$tlh][code_text];
	
	    if ($claim->procs[$tlh][code_type] == 'HCPCS') {
		    $tmpcode = '3';
	    }
	    else
	    {
		    $tmpcode = '1';
	    }
		    $getrevcd = $claim->cptCode($tlh);
		    $sql = "SELECT * FROM codes WHERE code_type = ? and code = ? ORDER BY related_code DESC";
		    $revcode[$tlh] = sqlQuery($sql, array($tmpcode,$getrevcd) );
        }
  
 
  
  for ($prockey = 0; $prockey < $proccount; ++$prockey) {
    ++$loopcount;

    ++$edicount;
    $out .= "LX" .      // Loop 2400 LX Service Line. Page 398.
      "*$loopcount" .
      "~\n";

    ++$edicount;
	
	// Need to add revenue code to this loop
	//
	$tmp = $revcode[$prockey][related_code];
	
    $out .= "SV2" .     // Institutional Service Line.
	  "*" . substr($tmp,14,4) . // revenue code
      "*HC:" . $claim->cptKey($prockey) .
      "*" . sprintf('%.2f', $claim->cptCharges($prockey)) .
      "*UN" .
      "*" . $claim->cptUnits($prockey) .
      "*" .
      "*" .
      "*";
//    $dia = $claim->diagIndexArray($prockey);
//    $i = 0;
//    foreach ($dia as $dindex) {
//      if ($i) $out .= ':';
//      $out .= $dindex;
//      if (++$i >= 4) break;
//    }
    $out .= "~\n";

    if (!$claim->cptCharges($prockey)) {
      $log .= "*** Procedure '" . $claim->cptKey($prockey) . "' has no charges!\n";
    }

    if (empty($dia)) {
      $log .= "*** Procedure '" . $claim->cptKey($prockey) . "' is not justified!\n";
    }

    // Segment SV5 (Durable Medical Equipment Service) omitted.
    // Segment PWK (Line Supplemental Information) omitted.
    // Segment PWK (Durable Medical Equipment Certificate of Medical Necessity Indicator) omitted.
    // Segment CR1 (Ambulance Transport Information) omitted.
    // Segment CR3 (Durable Medical Equipment Certification) omitted.
    // Segment CRC (Ambulance Certification) omitted.
    // Segment CRC (Hospice Employee Indicator) omitted.
    // Segment CRC (Condition Indicator / Durable Medical Equipment) omitted.

    ++$edicount;
	
	// ok for 837I
	//
	
    $out .= "DTP" .     // Date of Service. Page 435.
      "*472" .
      "*D8" .
      "*" . $claim->serviceDate() .
      "~\n";

    $testnote = rtrim($claim->cptNotecodes($prockey));
    if (!empty($testnote)) {
      ++$edicount;
      $out .= "NTE" .     // Explain Unusual Circumstances.
        "*ADD" .
        "*" . $claim->cptNotecodes($prockey) .
        "~\n";
    }
      
    // Segment DTP*471 (Prescription Date) omitted.
    // Segment DTP*607 (Revision/Recertification Date) omitted.
    // Segment DTP*463 (Begin Therapy Date) omitted.
    // Segment DTP*461 (Last Certification Date) omitted.
    // Segment DTP*304 (Last Seen Date) omitted.
    // Segment DTP (Test Date) omitted.
    // Segment DTP*011 (Shipped Date) omitted.
    // Segment DTP*455 (Last X-Ray Date) omitted.
    // Segment DTP*454 (Initial Treatment Date) omitted.
    // Segment QTY (Ambulance Patient Count) omitted.
    // Segment QTY (Obstetric Anesthesia Additional Units) omitted.
    // Segment MEA (Test Result) omitted.
    // Segment CN1 (Contract Information) omitted.
    // Segment REF*9B (Repriced Line Item Reference Number) omitted.
    // Segment REF*9D (Adjusted Repriced Line Item Reference Number) omitted.
    // Segment REF*G1 (Prior Authorization) omitted.
    // Segment REF*6R (Line Item Control Number) omitted.
    //   (Really oughta have this for robust 835 posting!)
    // Segment REF*EW (Mammography Certification Number) omitted.
    // Segment REF*X4 (CLIA Number) omitted.
    // Segment REF*F4 (Referring CLIA Facility Identification) omitted.
    // Segment REF*BT (Immunization Batch Number) omitted.
    // Segment REF*9F (Referral Number) omitted.
    // Segment AMT*GT (Sales Tax Amount) omitted.
    // Segment AMT*F4 (Postage Claimed Amount) omitted.
    // Segment K3 (File Information) omitted.
    // Segment NTE (Line Note) omitted.
    // Segment NTE (Third Party Organization Notes) omitted.
    // Segment PS1 (Purchased Service Information) omitted.
    // Segment HCP (Line Pricing/Repricing Information) omitted.

    if (!$CMS_5010) {
      // This segment was deleted for 5010.
      //
      // AMT*AAE segment for Approved Amount from previous payer.
      // Medicare secondaries seem to require this.
      //
      for ($ins = $claim->payerCount() - 1; $ins > 0; --$ins) {
        if ($claim->payerSequence($ins) > $claim->payerSequence())
          continue; // payer is future, not previous
        $payerpaid = $claim->payerTotals($ins, $claim->cptKey($prockey));
        ++$edicount;
        $out .= "AMT" . // Approved amount per previous payer. Page 485.
          "*AAE" .
          "*" . sprintf('%.2f', $claim->cptCharges($prockey) - $payerpaid[2]) .
          "~\n";
        break;
      }
    }

    // Loop 2410, Drug Information. Medicaid insurers seem to want this
    // with HCPCS codes.
    //
    $ndc = $claim->cptNDCID($prockey);
    if ($ndc) {
      ++$edicount;
      $out .= "LIN" . // Drug Identification. Page 500+ (Addendum pg 71).
        "*" .         // Per addendum, LIN01 is not used.
        "*N4" .
        "*" . $ndc .
        "~\n";

      if (!preg_match('/^\d\d\d\d\d-\d\d\d\d-\d\d$/', $ndc, $tmp) && !preg_match('/^\d{11}$/', $ndc)) {
        $log .= "*** NDC code '$ndc' has invalid format!\n";
      }

      ++$edicount;
      $tmpunits = $claim->cptNDCQuantity($prockey) * $claim->cptUnits($prockey);
      if (!$tmpunits) $tmpunits = 1;
      $out .= "CTP" . // Drug Pricing. Page 500+ (Addendum pg 74).
        "*" .
        "*" .
        "*" . ($CMS_5010 ? '' : sprintf('%.2f', $claim->cptCharges($prockey) / $tmpunits)) .
        "*" . $claim->cptNDCQuantity($prockey) .
        "*" . $claim->cptNDCUOM($prockey) .
        // Note: 5010 documents "ME" (Milligrams) as an additional unit of measure.
        "~\n";
    }

    // Segment REF (Prescription or Compound Drug Association Number) omitted.

    // Loop 2420A, Rendering Provider (service-specific). (Operating Physician Name for 837I)
    // Used if the rendering provider for this service line is different
    // from that in loop 2310B.
    //
    if ($claim->providerNPI() != $claim->providerNPI($prockey)) {
      ++$edicount;
      $out .= "NM1" .       // Loop 2310B Rendering Provider
        "*82" .
        "*1" .
        "*" . $claim->providerLastName($prockey) .
        "*" . $claim->providerFirstName($prockey) .
        "*" . $claim->providerMiddleName($prockey) .
        "*" .
        "*";
      if ($CMS_5010 || $claim->providerNPI($prockey)) { $out .=
        "*XX" .
        "*" . $claim->providerNPI($prockey);
      } else { $out .=
        "*34" .                         // Not allowed for 5010
        "*" . $claim->providerSSN($prockey);
      }
      if (!$claim->providerNPI($prockey)) {
        $log .= "*** Rendering provider has no NPI.\n";
      }
      $out .= "~\n";

      if ($claim->providerTaxonomy($prockey)) {
        ++$edicount;
        $out .= "PRV" .
          "*PE" . // PErforming provider
          "*" . ($CMS_5010 ? "PXC" : "ZZ") .
          "*" . $claim->providerTaxonomy($prockey) .
          "~\n";
      }

      // Segment PRV*PE (Rendering Provider Specialty Information) omitted.
      // Segment REF (Rendering Provider Secondary Identification) omitted.
      // Segment NM1 (Purchased Service Provider Name) omitted.
      // Segment REF (Purchased Service Provider Secondary Identification) omitted.
      // Segment NM1,N3,N4 (Service Facility Location) omitted.
      // Segment REF (Service Facility Location Secondary Identification) omitted.
      // Segment NM1 (Supervising Provider Name) omitted.
      // Segment REF (Supervising Provider Secondary Identification) omitted.
      // Segment NM1,N3,N4 (Ordering Provider) omitted.
      // Segment REF (Ordering Provider Secondary Identification) omitted.
      // Segment PER (Ordering Provider Contact Information) omitted.
      // Segment NM1 (Referring Provider Name) omitted.
      // Segment REF (Referring Provider Secondary Identification) omitted.
      // Segments NM1*PW, N3, N4 (Ambulance Pick-Up Location) omitted.
      // Segments NM1*45, N3, N4 (Ambulance Drop-Off Location) omitted.

      // REF*1C is required here for the Medicare provider number if NPI was
      // specified in NM109.  Not sure if other payers require anything here.
      if (!$CMS_5010 && $claim->providerNumber($prockey)) {
        ++$edicount;
        $out .= "REF" .
          "*" . $claim->providerNumberType($prockey) .
          // Note: 5010 documents that type 1D (Medicaid) is changed to G2.
          "*" . $claim->providerNumber($prockey) .
          "~\n";
      }
    }

    // Loop 2430, adjudication by previous payers.
    //
    for ($ins = 1; $ins < $claim->payerCount(); ++$ins) {
      if ($claim->payerSequence($ins) > $claim->payerSequence())
        continue; // payer is future, not previous

      $payerpaid = $claim->payerTotals($ins, $claim->cptKey($prockey));
      $aarr = $claim->payerAdjustments($ins, $claim->cptKey($prockey));

      if ($payerpaid[1] == 0 && !count($aarr)) {
        $log .= "*** Procedure '" . $claim->cptKey($prockey) .
          "' has no payments or adjustments from previous payer!\n";
        continue;
      }

      ++$edicount;
      $out .= "SVD" . // Service line adjudication. Page 554.
        "*" . $claim->payerID($ins) .
        "*" . $payerpaid[1] .
        "*HC:" . $claim->cptKey($prockey) .
        "*" .
        "*" . $claim->cptUnits($prockey) .
        "~\n";

      $tmpdate = $payerpaid[0];
      foreach ($aarr as $a) {
        ++$edicount;
        $out .= "CAS" . // Previous payer's line level adjustments. Page 558.
          "*" . $a[1] .
          "*" . $a[2] .
          "*" . $a[3] .
          "~\n";
        if (!$tmpdate) $tmpdate = $a[0];

        // WTH is this??
        /*************************************************************
        if ( isset($a[4]) &&
        	$a[4] != null ) {
        	$out .= "CAS02" . // Previous payer's adjustment reason
	          "*" . $a[4] .
	          "~\n";	
        }
        *************************************************************/
      }

      if ($tmpdate) {
        ++$edicount;
        $out .= "DTP" . // Previous payer's line adjustment date. Page 493/566.
          "*573" .
          "*D8" .
          "*$tmpdate" .
          "~\n";
      }

      // Segment AMT*EAF (Remaining Patient Liability) omitted.
      // Segment LQ (Form Identification Code) omitted.
      // Segment FRM (Supporting Documentation) omitted.

    } // end loop 2430
  } // end this procedure

  ++$edicount;
  $out .= "SE" .        // SE Trailer
    "*$edicount" .
    "*0021" .
    "~\n";

  $out .= "GE" .        // GE Trailer
    "*1" .
    "*1" .
    "~\n";

  $out .= "IEA" .       // IEA Trailer
    "*1" .
    "*000000001" .
    "~\n";

  // Remove any trailing empty fields (delimiters) from each segment.
  $out = preg_replace('/\*+~/', '~', $out);

  $log .= "\n";
  return $out;
}
?>
