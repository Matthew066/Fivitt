/*------------------------------------- Preloader -------------------------------------*/
$(window).on("load" , function () {
  $('.loader-mask1').delay(2000).fadeOut(3000);
});

$(window).on("load", function() {
  $('.preloader').delay(1000).fadeOut(1000); 
});

/*------------------------------------- Onboarding Screen -------------------------------------*/
$(document).on('click', '.skip_btn_1', function(){
  $("#first").removeClass("active");
  $(".first_slide").removeClass("active");  
  $("#second").addClass("active");
  $(".second_slide").addClass("active");
});

$(document).on('click', '.skip_btn_2', function(){
  $("#second").removeClass("active");
  $(".second_slide").removeClass("active");
  $("#third").addClass("active");
  $(".third_slide").addClass("active");
});

/*------------------------------------- Show Hide Password -------------------------------------*/
$(document).on('click', '#eye, #eye1', function() {
  $(this).toggleClass("fa-eye fa-eye-slash");
  var input = $(this).parent().find("input");
  if (input.attr("type") === "password") {
    input.attr("type", "text");
  } else {
    input.attr("type", "password");
  }
});

/*-------------------------------------OTP Section-------------------------------------*/
$(document).ready(function() {
  if ($('#otp').length > 0) {
    $('.digit-group').find('input').each(function() {
      $(this).attr('maxlength', 1);
      
      // Check if input already has a value and add the "filled" class
      if ($(this).val() !== '') {
        $(this).addClass('filled');
      }
      
      $(this).on('keyup', function(e) {
        var thisVal = $(this).val();
        var parent = $($(this).parent());
        
        if (e.keyCode === 8 || e.keyCode === 37) {
          $(this).removeClass('filled'); // Remove class from current input
          var prev = parent.find('input#' + $(this).data('previous'));
          if (prev.length) {
            $(prev).select();
          }
        } else {
          var next = parent.find('input#' + $(this).data('next'));

          if (!$.isNumeric(thisVal)) {
            $(this).val('');
            return false;
          }

          if (next.length) {
            $(this).addClass('filled'); // Add class to current input
            $(next).select();
          } else {
            $(this).addClass('filled'); // Add class to current input if it's the last one
            if (parent.data('autosubmit')) {
              parent.submit();
            }
          }
        }
      });
    });
  }
});

/*------------------------------------- Delete Or Deactive -------------------------------------*/
function continueAction() {
  const form = document.getElementById('deleteDeactivateForm');
  const selectedAction = form.querySelector('input[name="action"]:checked').value;
  if (selectedAction === 'delete') {
    window.location.href = 'delete-account.html';
  } else if (selectedAction === 'deactivate') {
    window.location.href = 'deactive-account.html';
  }
}

$(document).on('click', '#delete-deactivate-main .custom-radio', function(){
  $("#delete-deactivate-main .custom-radio").removeClass("active");
  $(this).addClass("active");
});

/*------------------------------------- Invite friend -------------------------------------*/
$(document).ready(function() {
  $(document).on('click', '.friend-select input', function() {
    var content = $(this);
    if (content.is(":checked")) {
      content.parent().addClass("active");
      content.parent().siblings().children(".custom-radio-sel-friend").addClass("active");
    } else {
      content.parent().siblings().children(".custom-radio-sel-friend").removeClass("active");
      content.parent().removeClass("active");
    }

    if (content.parent().hasClass('active')) {
      content.parent().children(".custom-radio-sel-friend").text("Invite");
    } else {
      content.parent().children(".custom-radio-sel-friend").text("Invited");
    }
  });
});

/*-------------------------------------Faq Section-------------------------------------*/
$(document).ready(function() {
  $('.nested-accordion').find('.comment').slideUp();
  $(document).on('click', '.nested-accordion h3', function() {
    $(this).next('.comment').slideToggle(100);
    $(this).toggleClass('selected');
  });
});

/*-------------------------------------  Currency AND language Checkbox-------------------------------------*/
$(document).ready(function() {
  $(document).on('click', '#language-screen input[type="radio"], #currency-page input[type="radio"]', function() {
    var $radioButtons = $('#language-screen input[type="radio"], #currency-page input[type="radio"]');
    $radioButtons.each(function() {
      $(this).parent().toggleClass('language-sel', this.checked);
    });
  });
});

/*------------------------------------- Payment  -------------------------------------*/
// Add Text In Card
function updateLokiBox(lokiBoxId, inputField) {
  document.getElementById(lokiBoxId).innerText = inputField.value;
}

// Add Card Number 16 digit
function maskNumber() {
  let inputNumber = document.getElementById('cardNumber').value;
  let digitsOnly = inputNumber.replace(/\D/g, '');
  let maskedPart = digitsOnly.substring(0, 12).replace(/./g, '*');
  let lastPart = digitsOnly.substring(12);
  let maskedNumber = maskedPart.replace(/(.{4})/g, '$1 ').trim() + ' ' + lastPart;
  document.getElementById('lokiCardNumber').textContent = maskedNumber;
}

// Add CVV Number Only Three
function validateInputcvv(inputField) {
  inputField.value = inputField.value.replace(/\D/g, '');
  if (inputField.value.length > 3) {
    inputField.value = inputField.value.slice(0, 3);
  }
  document.getElementById('lokiCVV').textContent = inputField.value;
}

/*-------------------------------------Toggle method -------------------------------------*/
function toggleConnection(element) {
  let isConnected = element.innerText === 'Connected';
  isConnected = !isConnected;
  if (isConnected) {
    element.innerText = 'Connected';
    element.style.color = '#00A266';
    element.style.cursor = 'pointer'
  } else {
    element.innerText = 'Not Connected';
    element.style.color = '#FB4945';
    element.style.cursor = 'pointer';
  }
}

/*------------------------------------- Circular Progree bar -------------------------------------*/
$(".circle_percent").each(function() {
  var $this = $(this),
      $dataV = $this.data("percent"),
      $dataDeg = $dataV * 3.6,
      $round = $this.find(".round_per");

  $round.css("transform", "rotate(" + parseInt($dataDeg + 180, 10) + "deg)"); 
  $this.append('<div class="circle_inbox"><span class="percent_text"></span></div>');
  $this.prop('Counter', 0).animate({ Counter: $dataV },
  {
    duration: 2000, 
    easing: 'swing', 
    step: function (now) {
      $this.find(".percent_text").text(Math.ceil(now) + "%");
    },
    complete: function() {
        // This function is called after the animation completes
        setTimeout(function() {
          window.location.href = 'workout-plan-ready.html'; // Change this URL to your desired URL
        }, 1000); // Redirects after 1 second
      }
    });
  
  if ($dataV >= 51) {
    $round.css("transform", "rotate(" + 360 + "deg)");
    setTimeout(function() {
      $this.addClass("percent_more");
    }, 1000);
    setTimeout(function() {
      $round.css("transform", "rotate(" + parseInt($dataDeg + 180, 10) + "deg)");
    }, 1000);
  }
});

/*------------------------------------- Face recognition -------------------------------------*/
$(document).ready(function() {
  const loader = $("#loader");
  let load = 0; 
  const intervalTime = 50; 
  const interval = setInterval(function () {
    loader.text(`${load} %`);
    if (load < 100) {
      load++;
    } else {
            clearInterval(interval); // Stop the loader at 100%
          }
        }, intervalTime);
});

/*------------------------------------- Select gender -------------------------------------*/
$(document).ready(function() {
  $(document).on('click', '.option', function() {
    $('.option').removeClass('selected');
    $(this).addClass('selected');
  });

  $(document).on('click', '.goal', function() {
    $(this).toggleClass('selected');
  });
});

/*------------------------------------- Redirect -------------------------------------*/
$(".shoulder-redirect").wrap('<a href="workout-category1.html"></a>');
$(".chest-redirect").wrap('<a href="workout-category2.html"></a>');
$(".leg-redirect").wrap('<a href="workout-category3.html"></a>');
$(".back-redirect").wrap('<a href="workout-category4.html"></a>');
$(".stomach-redirect").wrap('<a href="workout-category5.html"></a>');
$(".arms-redirect").wrap('<a href="workout-category6.html"></a>');
$(".single-exercise1-redirect").wrap('<a href="single-exercise1.html"></a>');
$(".legs").wrap('<a href="legs-details.html"></a>');
$(".shoulder").wrap('<a href="shoulder-details.html"></a>');
$(".chest").wrap('<a href="chest-details.html"></a>');
$(".back").wrap('<a href="back-details.html"></a>');
$(".stomach").wrap('<a href="stomach-details.html"></a>');
$(".arms").wrap('<a href="arms-details.html"></a>');

/*------------------------------------- Selection -------------------------------------*/
function selectGender(gender) {
  $('#male').removeClass('selected-img');
  $('#female').removeClass('selected-img');

  $('#' + gender).addClass('selected-img');

  if (gender === 'male') {
    $('#male-img').attr('src', 'assets/images/select-gender/male-select-img.png');
    $('#female-img').attr('src', 'assets/images/select-gender/female.png');
  } else if (gender === 'female') {
    $('#female-img').attr('src', 'assets/images/select-gender/female-sellect-img.png');
    $('#male-img').attr('src', 'assets/images/select-gender/male.png');
  }
}
$(document).ready(function() {
  selectGender('female'); 
});

/*------------------------------------- Profile Upload -------------------------------------*/
$(document).ready(function () {
  // Function to read and display the selected image
  var readURL = function (input) {
    if (input.files && input.files[0]) {
      var reader = new FileReader();
      reader.onload = function (e) {
        $('.profile-pic').attr('src', e.target.result);
      }
      reader.readAsDataURL(input.files[0]);
    }
  };

  // Handle file input change
  $(document).on('change', '.file-upload', function () {
    readURL(this);
  });

  // Trigger file input click on button click
  $(document).on('click', '.upload-button', function () {
    $('.file-upload').click();
  });
});

/*------------------------------------- Progress Bar -------------------------------------*/
$(document).ready(function() {
  var totalPages = 10;
  var currentPage = 1;

  function updateProgressBar() {
    var percentage = (currentPage / totalPages) * 100;
    $('#progress-bar').css('width', percentage + '%');
    $('#progress-bar').text(currentPage + '/' + totalPages);
  }

  // Event delegation for next-page buttons
  $(document).on('click', '.next-page', function() {
    if (currentPage < totalPages) {
      currentPage++;
      updateProgressBar();
    }
  });

  // Initial update
  updateProgressBar();
});
