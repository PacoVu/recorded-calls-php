window.onload = init;
const OK = 0
const FAILED = 1

function init() {
  $( "#fromdatepicker" ).datepicker({ dateFormat: "yy-mm-dd"});
  $( "#todatepicker" ).datepicker({dateFormat: "yy-mm-dd"});
  var pastMonth = new Date();
  var day = pastMonth.getDate()
  var month = pastMonth.getMonth() - 1
  var year = pastMonth.getFullYear()
  if (month < 0){
    month = 11
    year -= 1
  }
  $( "#fromdatepicker" ).datepicker('setDate', new Date(year, month, day));
  $( "#todatepicker" ).datepicker('setDate', new Date());
}
/*
function displayOnTable(response){
  $("#records_list").empty()
  var jsonObj = JSON.parse(response)
  for (var item of jsonObj){
    var id = document.createElement("td");
    id.text(item.id)
    var uri = document.createElement("td");
    uri.text(item.uri)
    $("#records_list").append(id)
    $("#records_list").append(uri)
  }
}
*/
function readCallLogs(){
  var configs = {}
  configs['recordingType'] = $('#recordingType').val()
  configs['dateFrom'] = $("#fromdatepicker").val() + "T00:00:00.000Z"
  configs['dateTo'] = $("#todatepicker").val() + "T23:59:59.999Z"
  configs['perPage'] = 1000

  var url = "engine.php?readlogs&access=" + $('#access_level').val();
  var posting = $.post( url, configs );
  posting.done(function( response ) {
      if (response.status == FAILED){
        alert(res.calllog_error)
      }else{
        window.location = "recordedcalls.php";
      }
  });
  posting.fail(function(response){
    alert(response.statusText);
  });
}

function transcribe(audioId){
  var configs = {}
  configs['audioId'] = audioId
  var url = "engine.php?transcribe"
  var posting = $.post( url, configs );
  posting.done(function( response ) {
    if (response.status == FAILED) {
      alert(response.message)
    }else{
      $('#tt_' + audioId).html(response.message)
      $('#tt_' + audioId).show()
      $('#te_' + audioId).hide()
    }
  });
  posting.fail(function(response){
    alert(response.statusText);
  });
}
