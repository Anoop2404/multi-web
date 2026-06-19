'use strict'

$(document).ready(function () {

    $('.input-name').on('keypress', function (e) {
        var charCode = e.which || e.keyCode;
        var inputVal = $(this).val();

        // Prevent space as first character
        if (charCode === 32 && inputVal.length === 0) {
            e.preventDefault();
            return false;
        }

        // Prevent double spaces
        if (charCode === 32 && inputVal.slice(-1) === ' ') {
            e.preventDefault();
            return false;
        }

        // Allow alphabetic characters (A-Z, a-z) and dot (.)
        if (
            (charCode >= 65 && charCode <= 90) || // A-Z
            (charCode >= 97 && charCode <= 122) || // a-z
            charCode === 46 || // Dot (.)
            charCode === 32 // Single space
        ) {
            return true; // Allow the keypress
        }

        // Prevent any other character
        e.preventDefault();
    });


    $.validator.addMethod("validate_email", function (value, element) {

        if (/^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/.test(value)) {
            return true;
        } else {
            return false;
        }
    }, "Enter a valid email.");

    $('.phone').on('input', function () {
        var value = $(this).val();

        // Remove all non-digit characters
        value = value.replace(/\D/g, '');
        // If the value exceeds 10 digits, trim it
        if (value.length > 10) {
            value = value.substring(0, 10);
        }

        // Set the value back to the input field
        $(this).val(value);
    });


    $('.email-validate').on('keypress', function () {
        if (/^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/.test(value)) {
            return true;
        } else {
            return false;
        }
    });

    $("input").on("keypress", function (e) {
        // If the value is empty (i.e., no text entered yet) and the user tries to type a space (keycode 32), prevent it
        if ($(this).val().length === 0 && e.which === 32) {
            e.preventDefault(); // Prevent space

        }
    });



    $("#contactformsbt").validate({
        rules: {
            yourname: {
                required: true,
            },
            emailid: {
                required: true,
                validate_email: true,
            },
            subject: {
                required: true,
            },
            message: {
                required: true,
            }


        },
        messages: {

            yourname: {
                required: 'Enter Name',
                maxlength: 'Maximum 100 characters allowed',
            },
            emailid: {
                required: 'Enter email',
                validate_email: 'Enter valid email',
            },
            subject: {
                required: 'Enter Subject',

            },
            message: {
                required: 'Enter Message',
            }



        },
        submitHandler: function (form) {
            form.submit(); // If validation passes, submit the form
        },
        invalidHandler: function (event, validator) {

            return false; // This prevents the form from being submitted
        }
    });



});