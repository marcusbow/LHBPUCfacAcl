<?php
/**
 *
 * Copyright (C) 2016-2017 Jerry Padgett <sjpadgett@gmail.com>
 *
 * LICENSE: This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0
 * See the Mozilla Public License for more details.
 * If a copy of the MPL was not distributed with this file, You can obtain one at https://mozilla.org/MPL/2.0/.
 *
 * @package LibreHealth EHR
 * @author Jerry Padgett <sjpadgett@gmail.com>
 * @link http://librehealth.io
 */

$this->assign( 'title', xlt("Portal Dashboard") . " | " . xlt("Home"));
$this->assign( 'nav', 'home' );
$this->display( '_ProviderHeader.tpl.php' );
echo "<script>var cpid='" . attr($this->cpid) . "';var cuser='" . attr($this->cuser) . "';var webRoot='" . $GLOBALS['web_root'] . "';</script>";
?>
<script>
$LAB.script("../sign/assets/signpad.js").wait(function(){
$(document).ready(function(){
      $('#openSignModal').on('show.bs.modal', function(e) {
            $('.sigPad').signaturePad({
                drawOnly: true,
                defaultAction: 'drawIt'
            });
       });
});
});
</script>
<div class="modal fade" id="formdialog" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" style="background:white">
        <div class="modal-content">
            <div class="modal-header">
                <!-- --><button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><?php echo xlt('About Portal Dashboard') ?></h4>
            </div>
        </div>
        <div class="modal-body">
            <div><span><?php echo xlt('Help content goes here'); ?></span></div>
        </div>
        <div class="modal-footer">
            <button id="okButton" data-dismiss="modal" class="btn btn-secondary"><?php echo xlt('Close...') ?></button>
        </div>
    </div>
</div>
<div class="container bg-info">
    <div class='well'>
    <div class="jumbotron text-center">
        <h3>
            <?php echo xlt('Portal Dashboard') ?><i class="fa fa-user-md" style="font-size:60px;color:red"></i>
        </h3>
        <p>
        <a class="btn btn-info" data-toggle="modal"
            data-target="#formdialog" href="#"><?php echo xlt('Tell me more') ?> »</a></p>
    </div>
</div>
<div class='well'>
    <div class="row">
        <div class="col-sm-3 col-md-3">
            <h4>
                <i class="icon-cogs"></i><?php echo xlt('Patient Document Templates') ?>
            </h4>
            <a class="btn btn-success btn-sm" href="<?php echo $GLOBALS['web_root']; ?>/patient_portal/import_template_ui.php"><?php echo xlt('Manage Templates') ?> »</a>
        </div>
        <div class="col-sm-3 col-md-3">
            <h4>
                <i class="icon-th"></i><?php echo xlt('Audit Changes') ?>
            </h4>
            <a class="btn btn-success btn-sm" href="<?php echo $GLOBALS['web_root']; ?>/patient_portal/patient/onsiteactivityviews"><?php echo xlt('Review Audits') ?> »</a>
        </div>
                <div class="col-sm-3 col-md-3">
            <h4>
                <i class="icon-cogs"></i><?php echo xlt('Patient Mail') ?>
            </h4>
            <a class="btn btn-success btn-sm" href="<?php echo $GLOBALS['web_root']; ?>/patient_portal/messaging/messages.php"><?php echo xlt('Mail') ?> »</a>
        </div>
        <div class="col-sm-3 col-md-3">
            <h4>
                <i class="icon-cogs"></i><?php echo xlt('Patient Chat') ?>
            </h4>
            <a class="btn btn-success btn-sm" href="<?php echo $GLOBALS['web_root']; ?>/patient_portal/messaging/secure_chat.php"><?php echo xlt('Messaging') ?> »</a>
        </div>
        <div class="col-sm-3 col-md-3">
            <h4>
                <i class="icon-signin"></i><?php echo xlt('User Signature') ?>
            </h4>
            <p>
            <a class="btn btn-primary btn-sm" href="#openSignModal" data-toggle="modal" data-backdrop="true" data-target="#openSignModal">
             <span><?php echo xlt('Signature on File') . '  '; ?></span><i  class="fa fa-sign-in"></i></a>
            </p>
        </div>

    </div>
</div>
<div id="openSignModal" class="modal fade" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <div class="input-group">
                    <span class="input-group-addon"
                        onclick="getSignature(document.getElementById('patientSignature'))"><em><?php echo xlt('Show Current Signature On File') ?><br>
                        <?php echo xlt('As will appear on documents.') ?>
                    </em></span> <img class="signature form-control" type="admin-signature" id="patientSignature"
                        onclick="getSignature(this)" alt="<?php echo xlt('Signature On File'); ?>" src="">
                    <!-- <span class="input-group-addon" onclick="clearSig(this)"><i class="glyphicon glyphicon-trash"></i></span> -->
                </div>
                <!-- <h4 class="modal-title">Sign</h4> -->
            </div>
            <div class="modal-body">
                <form name="signit" id="signit" class="sigPad">
                    <input type="hidden" name="name" id="name" class="name">
                    <ul class="sigNav">
                        <li style='display: block;'><input style="display: block"
                            type="checkbox" id="isAdmin" name="isAdmin" checked="checked" disabled/><?php echo xlt('Authorizing Signature') ?></li>
                        <li class="clearButton"><a href="#clear"><button><?php echo xlt('Clear Pad') ?></button></a></li>
                    </ul>
                    <div class="sig sigWrapper">
                        <div class="typed"></div>
                        <canvas class="spad" id="drawpad" width="765" height="325"
                            style="border: 1px solid #000000; left: 0px;"></canvas>
                        <img id="loading"
                            style="display: none; position: absolute; TOP: 150px; LEFT: 315px; WIDTH: 100px; HEIGHT: 100px"
                            src="<?php echo $GLOBALS['web_root']; ?>/patient_portal/sign/assets/loading.gif" /> <input type="hidden" id="output" name="output" class="output">
                    </div>
                    <input type="hidden" name="type" id="type" value="patient-signature">
                    <button type="button" onclick="signDoc(this)"><?php echo xlt('Authorize as my Electronic Signature.') ?></button>
                </form>
            </div>
        </div>
        <!-- <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo xlt('Close') ?></button>
        </div> -->
    </div>
</div>
<!-- Modal -->
<img id="waitend"
    style="display: none; position: absolute; top: 100px; left: 250px; width: 100px; height: 100px"
    src="<?php echo $GLOBALS['web_root']; ?>/patient_portal/sign/assets/loading.gif" />
</div>
<!-- /container -->

<?php
$this->display( '_Footer.tpl.php' );
?>