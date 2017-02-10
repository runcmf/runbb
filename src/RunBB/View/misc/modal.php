<?php
/**
 * Copyright 2017 1f7.wizard@gmail.com
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

//$password = "paZZwordT1234";
//$_SERVER['PHP_AUTH_PW'] = $password;
//if (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_PW'] !== $password)
//{
//    header('WWW-Authenticate: Basic realm="NoConsoleComposer"');
//    header('HTTP/1.0 401 Unauthorized');
//    exit;
//}
?>

<script type="text/javascript">
    var csrf_name, csrf_value;
    var url = '<?= \RunBB\Core\Interfaces\Router::pathFor('pluginsCompose') ?>';
    var pluginInfo = '<?= $pluginInfo ?>';
    $(document).ready(function() {
        $("#output").html("Welcome. To Composer Helper\n");
        check();
    });
    // reload parent page after this modal close
    $('#modalInfo').on('hidden.bs.modal', function () { location.reload(); });
    // auto scroll
    function scroll() {
        $("#output").animate({ scrollTop:$("#output")[0].scrollHeight - $("#output").height() }, 800);
    }
    function call(func) {
        $("#output").append("\nplease wait...\n");
        $("#output").append("\n===================================================================\n");
        $("#output").append("Executing Started");
        $("#output").append("\n===================================================================\n");
        $.post(url, {
            "path":$("#path").val(),
            "command":func,
            "function": "command",
            "csrf_name": csrf_name,
            "csrf_value": csrf_value,
            "pluginInfo": pluginInfo
        },
        function(data) {
            $("#output").append(data);
            $("#output").append("\n===================================================================\n");
            $("#output").append("Execution Ended");
            $("#output").append("\n===================================================================\n");
            scroll();
            check();
        });
    }
    function check()
    {
        $("#output").append('\nloading...\n');
        $.get(url, {
            "function": "getStatus"//,
            //"password": $("#password").val()
        },
        function(response) {
            var data = JSON.parse(response);
            csrf_name = data.csrf_name;
            csrf_value = data.csrf_value;

            if (data.composer_extracted) {
                $("#output").append("Ready. All commands are available.\n");
                $("button").removeClass('disabled');
            } else if(data.composer) {
                $.post(url, {
                    //"password": $("#password").val(),
                    "function": "extractComposer",
                    "csrf_name": csrf_name,
                    "csrf_value": csrf_value
                },
                function(data) {
                    $("#output").append(data);
//                    window.location.reload();
                    scroll();
                    check();
                }, 'text');
            } else {
                $("#output").append("Please wait till composer is being installed...\n");
                $.post(url, {
                    //"password": $("#password").val(),
                    "function": "downloadComposer",
                    "csrf_name": csrf_name,
                    "csrf_value": csrf_value
                },
                function(data) {
                    $("#output").append(data);
                    check();
                }, 'text');
            }
        });
    }
    function confirmDelete() {
        if (confirm('Are you sure?')) {
            call('remove <?= $package ?>');
        } else {
            return false;
        }
    }
</script>
<style>
    #output {
        width:100%;
        max-width: 1000px;
        height:350px;
        overflow-y:scroll;
        overflow-x:scroll;
    }
</style>
<!-- Modal -->
<div class="modal fade" id="modalInfo" tabindex="-1" role="dialog"
     aria-labelledby="modalInfoLabel" aria-hidden="true">
    <div class="modal-dialog" style="width: 70% !important;">
        <div class="modal-content">
            <form role="form" method="get">
                <!-- Modal Header -->
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">
                        <span aria-hidden="true">&times;</span>
                        <span class="sr-only">Close</span>
                    </button>
                    <h4 class="modal-title" id="modalInfoLabel">
                        Composer helper
                    </h4>
                </div>

                <!-- Modal Body -->
                <div class="modal-body">
                    <div class="form-group">
                        <div class="input-group" style="width: 100%">

                            <div class="row">
                                <div class="col-lg-1"></div>
                                <div class="col-lg-10">
                                    <div class="form-inline">
                                        <button id="install" onclick="call('require <?= $package ?>:<?= $stability ?>'); return false;" class="btn btn-xs btn-success disabled">require <?= $package ?>:<?= $stability ?></button>
                                        <div class="pull-right">
                                            <button id="uninstall" onclick="confirmDelete(); return false;" class="btn btn-xs btn-danger disabled">remove <?= $package ?></button>
                                        </div>
                                    </div>
                                    <strong>Console Output:</strong>
                                    <pre id="output" class="well" style="width: 100%"></pre>
                                    <div class="form-inline">
                                        &nbsp; <button id="update" onclick="call('update'); return false;" class="btn btn-xs btn-info disabled">update</button>
                                        &nbsp; <button id="update" onclick="call('dump-autoload --optimize'); return false;" class="btn btn-xs btn-info disabled">dump-autoload --optimize</button>
                                        &nbsp; <button id="update" onclick="call('-V'); return false;" class="btn btn-xs btn-info disabled">version (-V)</button>
                                        &nbsp; <button id="update" onclick="call('status -v'); return false;" class="btn btn-xs btn-info disabled">status -v</button>
                                    </div>
                                </div>
                                <div class="col-lg-1"></div>
                            </div>

                        </div>
                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">
                        Close
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>
<!-- /Modal -->
