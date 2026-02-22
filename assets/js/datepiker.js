/*------------------------------------- Date Picker -------------------------------------*/
$(document).ready(function () {
  $("#datepicker").datepicker({
    dateFormat: "dd-y",
    duration: "fast",
    onSelect: function (dateText, inst) {
      $("#lokiExpiryDate").text(dateText);
    }
  });
});