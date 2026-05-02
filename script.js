const container = document.querySelector('.container');
const registerBtn = document.querySelector('.register-btn');
const loginBtn = document.querySelector('.login-btn');

registerBtn.addEventListener('click', () => {
    container.classList.add('active');
});

loginBtn.addEventListener('click', () => {
    container.classList.remove('active');
});

// Forgot Password Modal
var modal = document.getElementById('forgotModal');
var btn = document.getElementById('forgotPasswordLink');
var span = document.getElementsByClassName('close')[0];

btn.onclick = function (e) {
    e.preventDefault();
    modal.style.display = 'block';
}

span.onclick = function () {
    modal.style.display = 'none';
}

window.onclick = function (event) {
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}

// Google Login Handler (Demo - In production, use Google OAuth SDK)
// Replace your existing Google/Apple functions with these:

function handleGoogleLogin() {
    // Open a popup that looks realistic
    const googleWindow = window.open(
        'https://accounts.google.com/o/oauth2/v2/auth?client_id=YOUR_CLIENT_ID&redirect_uri=YOUR_REDIRECT_URI&response_type=code&scope=email profile',
        'Google Login',
        'width=500,height=600,left=100,top=100'
    );
    
    // For demo without real OAuth, show a better message
    alert("For demo: In production, this would open Google's real login page.\n\nTo test password reset, please use the 'Forgot password?' feature.");
}

function handleAppleLogin() {
    alert("For demo: In production, this would open Apple's real login page.\n\nTo test password reset, please use the 'Forgot password?' feature.");
}

function handleGoogleRegister() {
    handleGoogleLogin();
}

function handleAppleRegister() {
    handleAppleLogin();
}

// Auto-hide alerts after 5 seconds
setTimeout(function () {
    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function (alert) {
        alert.style.opacity = '0';
        setTimeout(function () {
            alert.style.display = 'none';
        }, 500);
    });
}, 5000);

// Role selection styling
document.querySelectorAll('.role-card').forEach(card => {
    const radio = card.querySelector('input[type="radio"]');
    radio.addEventListener('change', function () {
        document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
        if (this.checked) {
            card.classList.add('selected');
        }
    });
    if (radio.checked) {
        card.classList.add('selected');
    }
});