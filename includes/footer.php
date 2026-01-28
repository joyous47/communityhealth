<?php
?>
        </div>
        
        <footer class="main-footer">
            <div class="footer-container">
                <div class="footer-content">
                    <div class="footer-section">
                        <h4><i class="fas fa-shield-virus"></i> Community Health Monitoring System</h4>
                        <p>A comprehensive platform for monitoring, reporting, and analyzing disease outbreaks in your community.</p>
                        <div class="footer-stats">
                            <div class="footer-stat">
                                <i class="fas fa-users"></i>
                                <span>User Protection</span>
                            </div>
                            <div class="footer-stat">
                                <i class="fas fa-database"></i>
                                <span>Secure Data</span>
                            </div>
                            <div class="footer-stat">
                                <i class="fas fa-chart-line"></i>
                                <span>Real-time Analytics</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="footer-section">
                        <h4>Quick Links</h4>
                        <ul class="footer-links">
                            <li><a href="../index.php"><i class="fas fa-home"></i> Home</a></li>
                            <li><a href="../public/public_dashboard.php"><i class="fas fa-chart-bar"></i> Public Dashboard</a></li>
                            <?php if (!isLoggedIn()): ?>
                                <li><a href="../auth/login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                                <li><a href="../auth/register.php"><i class="fas fa-user-plus"></i> Register</a></li>
                            <?php endif; ?>
                            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </div>
                    
                    <div class="footer-section">
                        <h4>User Roles</h4>
                        <ul class="footer-links">
                            <li><a href="#"><i class="fas fa-user"></i> Citizen Portal</a></li>
                            <li><a href="#"><i class="fas fa-user-md"></i> Health Worker Portal</a></li>
                            <li><a href="#"><i class="fas fa-user-cog"></i> Admin Portal</a></li>
                        </ul>
                    </div>
                    
                    <div class="footer-section">
                        <h4>Contact & Support</h4>
                        <p><i class="fas fa-envelope"></i> support@diseasesurveillance.example</p>
                        <p><i class="fas fa-phone"></i> +1 (234) 567-8900</p>
                        <p><i class="fas fa-clock"></i> 24/7 Emergency Support</p>
                        <div class="social-links">
                            <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                            <a href="#" class="social-link"><i class="fab fa-facebook"></i></a>
                            <a href="#" class="social-link"><i class="fab fa-linkedin"></i></a>
                            <a href="#" class="social-link"><i class="fab fa-github"></i></a>
                        </div>
                    </div>
                </div>
                
                <div class="footer-bottom">
                    <div class="footer-info">
                        <p>&copy; <?php echo date('Y'); ?> Community Health Monitoring System. All rights reserved.</p>
                        <p>Built with PHP, MySQL, and Chart.js | Security: SQL Injection Protected | XSS Protected</p>
                    </div>
                    <div class="footer-legal">
                        <a href="#">Privacy Policy</a> | 
                        <a href="#">Terms of Service</a> | 
                        <a href="#">Cookie Policy</a>
                    </div>
                </div>
            </div>
        </footer>
        
        <script src="../assets/js/main.js"></script>
        <script src="../assets/js/validation.js"></script>
        <script src="../assets/js/charts.js"></script>
        
        <script>
            document.getElementById('mobileMenuToggle').addEventListener('click', function() {
                document.getElementById('navMenu').classList.toggle('show');
            });
            
            document.addEventListener('click', function(event) {
                const navMenu = document.getElementById('navMenu');
                const menuToggle = document.getElementById('mobileMenuToggle');
                
                if (!navMenu.contains(event.target) && !menuToggle.contains(event.target)) {
                    navMenu.classList.remove('show');
                }
            });
            
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                });
            }, 5000);
            
            let timeoutWarningShown = false;
            function checkSessionWarning() {
                setTimeout(() => {
                    if (!timeoutWarningShown && window.location.pathname.indexOf('logout') === -1) {
                        if (confirm('Your session will expire in 1 minute. Click OK to stay logged in.')) {
                            fetch('../includes/session_heartbeat.php')
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        alert('Session extended for 30 more minutes.');
                                        timeoutWarningShown = false;
                                        checkSessionWarning();
                                    }
                                });
                        }
                        timeoutWarningShown = true;
                    }
                }, 1740000);
            }
            
            <?php if (isLoggedIn()): ?>
            checkSessionWarning();
            <?php endif; ?>
            
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href');
                    if (targetId === '#') return;
                    
                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        window.scrollTo({
                            top: targetElement.offsetTop - 100,
                            behavior: 'smooth'
                        });
                    }
                });
            });
            
            function validateForm(formId, requiredFields) {
                const form = document.getElementById(formId);
                if (!form) return true;
                
                let isValid = true;
                requiredFields.forEach(fieldId => {
                    const field = document.getElementById(fieldId);
                    if (field && !field.value.trim()) {
                        field.style.borderColor = '#000000';
                        isValid = false;
                        
                        let errorMsg = field.nextElementSibling;
                        if (!errorMsg || !errorMsg.classList.contains('field-error')) {
                            errorMsg = document.createElement('div');
                            errorMsg.className = 'field-error';
                            errorMsg.style.color = '#000000';
                            errorMsg.style.fontSize = '0.9rem';
                            errorMsg.style.marginTop = '5px';
                            field.parentNode.insertBefore(errorMsg, field.nextSibling);
                        }
                        errorMsg.textContent = 'This field is required';
                    } else {
                        if (field) field.style.borderColor = '';
                        const errorMsg = field.nextElementSibling;
                        if (errorMsg && errorMsg.classList.contains('field-error')) {
                            errorMsg.remove();
                        }
                    }
                });
                
                return isValid;
            }
        </script>
    </body>
</html>