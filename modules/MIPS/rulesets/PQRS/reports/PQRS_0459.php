<?php
/**
 * PQRS Measure 0459 -- Call to createPopulationCriteria()
 *
 * Copyright (C) 2015 - 2018      Suncoast Connection
  * 
 * LICENSE: This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0
 * See the Mozilla Public License for more details. 
 * If a copy of the MPL was not distributed with this file, You can obtain one at https://mozilla.org/MPL/2.0/.
 * 
 * @author  Art Eaton <art@suncoastconnection.com>
 * @author  Bryan lee <bryan@suncoastconnection.com>
 * @package LibreHealthEHR 
 * @link    http://suncoastconnection.com
 * @link    http://librehealth.io
 *
 * Please support this product by sharing your changes with the LibreHealth.io community.
 */

class PQRS_0459 extends AbstractPQRSReport
{   
    public function createPopulationCriteria()
    {
        return new PQRS_0249_PopulationCriteria();
    }
}
