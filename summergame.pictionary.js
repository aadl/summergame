function pictionaryupdate() {
  $.getJSON('http://odie.aadl.org/~kloostere/beta/?q=summergame/pictionaryupdate', function(json) {
    $("div.autorefresh_div").hide().fadeIn("slow").html(json['html']);
  });
}
