<?php
require_once '../includes/header.php';
require_once '../config/database.php';

requireRole('citizen', '../auth/login.php');

$user = getCurrentUser();
$user_id = $user['id'];

$error = '';
$success = '';
$form_data = [
    'disease_name' => '',
    'symptoms' => '',
    'location' => '',
    'additional_info' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Security token invalid. Please try again.";
    } else {
        $disease_name = trim($_POST['disease_name'] ?? '');
        $symptoms = trim($_POST['symptoms'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $additional_info = trim($_POST['additional_info'] ?? '');
        
        if (empty($disease_name) || empty($symptoms) || empty($location)) {
            $error = "Please fill in all required fields (disease name, symptoms, and location).";
        } elseif (strlen($disease_name) > 100) {
            $error = "Disease name must be less than 100 characters.";
        } elseif (strlen($location) > 200) {
            $error = "Location must be less than 200 characters.";
        } elseif (strlen($symptoms) > 5000) {
            $error = "Symptoms description is too long.";
        } else {
            try {
                $db = getDB();
                
                $stmt = $db->prepare("INSERT INTO reports (citizen_id, disease_name, symptoms, location, status) VALUES (?, ?, ?, ?, 'pending')");
                $stmt->execute([$user_id, $disease_name, $symptoms, $location]);
                
                $report_id = $db->lastInsertId();
                
                $report_hour = date('G');
                $report_day = date('l');
                
                $stmt = $db->prepare("INSERT INTO analytics (report_id, report_hour, report_day) VALUES (?, ?, ?)");
                $stmt->execute([$report_id, $report_hour, $report_day]);
                
                $success = "Report submitted successfully! Your report ID is #" . $report_id . ". Health workers will review it soon.";
                
                $form_data = [
                    'disease_name' => '',
                    'symptoms' => '',
                    'location' => '',
                    'additional_info' => ''
                ];
                
            } catch(PDOException $e) {
                $error = "Error submitting report: " . $e->getMessage();
            }
        }
        
        $form_data = [
            'disease_name' => htmlspecialchars($disease_name),
            'symptoms' => htmlspecialchars($symptoms),
            'location' => htmlspecialchars($location),
            'additional_info' => htmlspecialchars($additional_info)
        ];
    }
}

$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Disease Report </title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8fbff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            margin: 0;
            padding: 0;
        }
        
        .create-report-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            background: linear-gradient(135deg, #87ceeb, #5ba4d6);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .page-header h2 {
            margin-bottom: 10px;
            font-size: 1.8rem;
        }
        
        .page-header p {
            opacity: 0.95;
            max-width: 600px;
            margin: 0 auto;
            font-size: 1rem;
        }
        
        .form-card {
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0, 119, 204, 0.1);
            margin-bottom: 30px;
            border: 1px solid #e0e0e0;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .form-section h3 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e8f4ff;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-section h3 i {
            color: #0077cc;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .required::after {
            content: " *";
            color: #d32f2f;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
            font-family: inherit;
            box-sizing: border-box;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #0077cc;
            box-shadow: 0 0 0 3px rgba(0, 119, 204, 0.2);
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            background-color: white;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .form-select:focus {
            outline: none;
            border-color: #0077cc;
            box-shadow: 0 0 0 3px rgba(0, 119, 204, 0.2);
        }
        
        .form-help {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: #e8f5e8;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }
        
        .alert-error {
            background-color: #ffebee;
            color: #d32f2f;
            border-left: 4px solid #d32f2f;
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .btn-submit {
            padding: 14px 30px;
            background-color: #0077cc;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-submit:hover {
            background-color: #005599;
            transform: translateY(-2px);
        }
        
        .btn-cancel {
            padding: 14px 30px;
            background-color: #666;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-cancel:hover {
            background-color: #555;
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
        }
        
        .symptom-examples {
            background-color: #f8fbff;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            border-left: 4px solid #0077cc;
        }
        
        .symptom-examples h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #333;
            font-size: 0.95rem;
        }
        
        .symptom-examples ul {
            margin: 0;
            padding-left: 20px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .symptom-examples li {
            margin-bottom: 5px;
        }
        
        .character-count {
            text-align: right;
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }
        
        .character-count.warning {
            color: #f39c12;
        }
        
        .character-count.error {
            color: #d32f2f;
        }
        
        .disease-suggestions {
            position: absolute;
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        
        .disease-suggestion {
            padding: 10px 15px;
            cursor: pointer;
            transition: background-color 0.2s;
            color: #333;
        }
        
        .disease-suggestion:hover {
            background-color: #f8fbff;
        }
        
        .info-card {
            background-color: #f8fbff;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            border: 1px solid #e0e0e0;
        }
        
        .info-card h3 {
            margin-top: 0;
            color: #333;
            margin-bottom: 20px;
            font-size: 1.3rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            text-align: center;
            padding: 15px;
            background-color: white;
            border-radius: 8px;
            border: 1px solid #e8f4ff;
            transition: transform 0.3s;
        }
        
        .info-item:hover {
            transform: translateY(-5px);
        }
        
        .info-icon {
            font-size: 2.5rem;
            color: #0077cc;
            margin-bottom: 10px;
        }
        
        .info-item h4 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 1rem;
        }
        
        .info-item p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .breadcrumb {
            margin-bottom: 25px;
            padding: 12px 20px;
            background-color: #f0f8ff;
            border-radius: 8px;
            font-size: 0.95rem;
            color: #333;
        }
        
        .breadcrumb a {
            color: #0077cc;
            text-decoration: none;
            margin: 0 5px;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
            color: #005599;
        }
        
        @media (max-width: 768px) {
            .create-report-container {
                padding: 15px;
            }
            
            .form-card {
                padding: 20px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn-submit, .btn-cancel {
                width: 100%;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="create-report-container">
            <div class="breadcrumb">
                <a href="../index.php"><i class="fas fa-home"></i> Home</a>
                <span>/</span>
                <a href="dashboard.php">Dashboard</a>
                <span>/</span>
                <span>Submit Report</span>
            </div>
            
            <div class="page-header">
                <h2><i class="fas fa-file-medical-alt"></i> Submit Disease Report</h2>
                <p>Help monitor disease outbreaks by reporting symptoms and concerns in your area. Your report will be reviewed by health professionals.</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <div class="form-card">
                <form method="POST" action="" id="reportForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    
                    <div class="form-section">
                        <h3><i class="fas fa-virus"></i> Disease Information</h3>
                        
                        <div class="form-group">
                            <label for="disease_name" class="required">Disease Name</label>
                            <input type="text" 
                                   id="disease_name" 
                                   name="disease_name" 
                                   class="form-control" 
                                   value="<?php echo $form_data['disease_name']; ?>" 
                                   required
                                   maxlength="100"
                                   placeholder="e.g., Influenza, COVID-19, Malaria">
                            <div class="form-help">
                                Enter the name of the disease or health concern. Be as specific as possible.
                            </div>
                            <div id="diseaseSuggestions" class="disease-suggestions"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="symptoms" class="required">Symptoms</label>
                            <textarea id="symptoms" 
                                      name="symptoms" 
                                      class="form-control" 
                                      required
                                      maxlength="5000"
                                      placeholder="Describe the symptoms you're experiencing..."><?php echo $form_data['symptoms']; ?></textarea>
                            <div class="character-count" id="symptomsCount">0 / 5000 characters</div>
                            <div class="symptom-examples">
                                <h4><i class="fas fa-lightbulb"></i> Common symptoms to include:</h4>
                                <ul>
                                    <li>Fever, cough, sore throat</li>
                                    <li>Body aches, fatigue, headache</li>
                                    <li>Difficulty breathing, chest pain</li>
                                    <li>Nausea, vomiting, diarrhea</li>
                                    <li>Skin rashes, swelling, redness</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3><i class="fas fa-map-marker-alt"></i> Location Information</h3>

                        <div class="form-group">
                            <label for="countrySelect" class="required">Country</label>
                            <input id="countrySearch" class="form-control" placeholder="Search country..." style="margin-bottom:8px;" />
                            <select id="countrySelect" name="country" class="form-select" required>
                                <option value="">Select a country</option>
                            </select>
                        </div>

                        <div id="kenyaCascade" style="display:none">
                            <div class="form-group">
                                <label for="countySelect">County</label>
                                <select id="countySelect" class="form-select cascade-select" data-level="county">
                                    <option value="">Select County</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="subcountySelect">Sub-County</label>
                                <select id="subcountySelect" class="form-select cascade-select" data-level="subcounty">
                                    <option value="">Select Sub-County</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="divisionSelect">Division / Ward</label>
                                <select id="divisionSelect" class="form-select cascade-select" data-level="division">
                                    <option value="">Select Division / Ward</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="locationSelect">Location</label>
                                <select id="locationSelect" class="form-select cascade-select" data-level="location">
                                    <option value="">Select Location</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="villageSelect">Village</label>
                                <select id="villageSelect" class="form-select cascade-select" data-level="village">
                                    <option value="">Select Village</option>
                                </select>
                            </div>
                        </div>

                        <div id="otherLocationWrapper" class="form-group" style="display:none;">
                            <label for="manual_location">Specify Location (Other / Not Listed)</label>
                            <input type="text" id="manual_location" class="form-control" placeholder="Enter your specific location (e.g., neighborhood, facility)">
                        </div>

                        <input type="hidden" id="locationHidden" name="location" value="<?php echo $form_data['location']; ?>">

                        <div class="form-group">
                            <label for="additional_info">Additional Information</label>
                            <textarea id="additional_info" 
                                      name="additional_info" 
                                      class="form-control" 
                                      maxlength="1000"
                                      placeholder="Any additional information that might be helpful..."><?php echo $form_data['additional_info']; ?></textarea>
                            <div class="character-count" id="additionalInfoCount">0 / 1000 characters</div>
                            <div class="form-help">
                                Optional: Include details about exposure, travel history, or other relevant information.
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> Submit Report
                        </button>
                        <a href="dashboard.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
            
            <div class="info-card">
                <h3><i class="fas fa-info-circle"></i> What happens after you submit?</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <h4>Review</h4>
                        <p>Health workers review your report within 24-48 hours.</p>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-stethoscope"></i>
                        </div>
                        <h4>Analysis</h4>
                        <p>Reports are analyzed and severity is assessed.</p>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-comment-medical"></i>
                        </div>
                        <h4>Recommendations</h4>
                        <p>You'll receive health recommendations based on analysis.</p>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h4>Monitoring</h4>
                        <p>Disease trends are monitored for public health response.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const symptomsTextarea = document.getElementById('symptoms');
            const symptomsCount = document.getElementById('symptomsCount');
            const additionalInfoTextarea = document.getElementById('additional_info');
            const additionalInfoCount = document.getElementById('additionalInfoCount');
            const diseaseInput = document.getElementById('disease_name');
            const suggestionsDiv = document.getElementById('diseaseSuggestions');

            function updateCharacterCount(textarea, countElement, maxLength) {
                const length = textarea.value.length;
                countElement.textContent = length + ' / ' + maxLength + ' characters';
                if (length > maxLength * 0.9) {
                    countElement.classList.add('warning');
                    countElement.classList.remove('error');
                } else if (length > maxLength) {
                    countElement.classList.add('error');
                    countElement.classList.remove('warning');
                } else {
                    countElement.classList.remove('warning', 'error');
                }
            }

            symptomsTextarea.addEventListener('input', function() { updateCharacterCount(this, symptomsCount, 5000); });
            additionalInfoTextarea.addEventListener('input', function() { updateCharacterCount(this, additionalInfoCount, 1000); });
            updateCharacterCount(symptomsTextarea, symptomsCount, 5000);
            updateCharacterCount(additionalInfoTextarea, additionalInfoCount, 1000);

            const commonDiseases = [
                'Influenza (Flu)', 'COVID-19', 'Common Cold', 'Pneumonia',
                'Malaria', 'Dengue Fever', 'Typhoid', 'Cholera',
                'Measles', 'Chickenpox', 'Tuberculosis', 'HIV/AIDS',
                'Hepatitis A', 'Hepatitis B', 'Diabetes', 'Hypertension',
                'Asthma', 'Bronchitis', 'Food Poisoning', 'Diarrhea'
            ];

            diseaseInput.addEventListener('input', function() {
                const value = this.value.toLowerCase();
                suggestionsDiv.innerHTML = '';
                if (value.length < 2) { suggestionsDiv.style.display = 'none'; return; }
                const filtered = commonDiseases.filter(d => d.toLowerCase().includes(value));
                if (filtered.length === 0) { suggestionsDiv.style.display = 'none'; return; }
                filtered.forEach(d => {
                    const div = document.createElement('div'); div.className = 'disease-suggestion'; div.textContent = d;
                    div.addEventListener('click', () => { diseaseInput.value = d; suggestionsDiv.style.display = 'none'; });
                    suggestionsDiv.appendChild(div);
                });
                suggestionsDiv.style.display = 'block';
                const rect = diseaseInput.getBoundingClientRect();
                suggestionsDiv.style.position = 'fixed';
                suggestionsDiv.style.top = (rect.bottom + window.scrollY) + 'px';
                suggestionsDiv.style.left = rect.left + 'px';
                suggestionsDiv.style.width = rect.width + 'px';
            });
            document.addEventListener('click', function(e) { if (!diseaseInput.contains(e.target) && !suggestionsDiv.contains(e.target)) suggestionsDiv.style.display = 'none'; });

            const countrySelect = document.getElementById('countrySelect');
            const countrySearch = document.getElementById('countrySearch');
            const kenyaCascade = document.getElementById('kenyaCascade');
            const countySelect = document.getElementById('countySelect');
            const subcountySelect = document.getElementById('subcountySelect');
            const divisionSelect = document.getElementById('divisionSelect');
            const locationSelect = document.getElementById('locationSelect');
            const villageSelect = document.getElementById('villageSelect');
            const otherWrapper = document.getElementById('otherLocationWrapper');
            const manualLocation = document.getElementById('manual_location');
            const locationHidden = document.getElementById('locationHidden');

            const kenyaSample = {
                "counties": [
                    {
                        "name": "Nairobi",
                        "subcounties": [
                            {"name":"Westlands","divisions":[{"name":"Kangemi","locations":[{"name":"Kangemi North","villages":["Village A","Village B"]}]}]},
                            {"name":"Kibra","divisions":[{"name":"Soweto","locations":[{"name":"Soweto East","villages":["Village C"]}]}]}
                        ]
                    },
                    {
                        "name": "Mombasa",
                        "subcounties": [
                            {"name":"Mvita","divisions":[{"name":"Town","locations":[{"name":"Old Town","villages":["Village X"]}]}]}
                        ]
                    }
                ]
            };

            let kenyaData = null;

            function populateSelect(select, items, placeholder) {
                select.innerHTML = '';
                const empty = document.createElement('option'); empty.value = ''; empty.textContent = placeholder || 'Select'; select.appendChild(empty);
                items.forEach(i => {
                    const opt = document.createElement('option'); opt.value = i.name || i; opt.textContent = i.name || i; select.appendChild(opt);
                });
                const other = document.createElement('option'); other.value = '__other__'; other.textContent = 'Other / Not Listed'; select.appendChild(other);
            }

            function resetBelow(level) {
                const order = ['county','subcounty','division','location','village'];
                const elems = {county: countySelect, subcounty: subcountySelect, division: divisionSelect, location: locationSelect, village: villageSelect};
                const idx = order.indexOf(level);
                for (let i = idx+1; i < order.length; i++) { elems[order[i]].innerHTML = '<option value="">Select</option><option value="__other__">Other / Not Listed</option>'; }
            }

            function tryLoadKenyaData() {
                fetch('../assets/data/kenya_locations.json').then(r=>{ if (!r.ok) throw new Error('no'); return r.json(); }).then(data=>{ kenyaData = data; initKenya(); }).catch(err=>{ console.log('Fallback to sample:', err); kenyaData = kenyaSample; initKenya(); });
            }

            function initKenya() {
                const counties = kenyaData.counties.map(c => ({name: c.name}));
                populateSelect(countySelect, kenyaData.counties, 'Select County');
            }

            countrySearch.addEventListener('input', function(){
                const q = this.value.toLowerCase();
                Array.from(countrySelect.options).forEach(opt => { opt.style.display = (opt.value && opt.value.toLowerCase().includes(q)) ? 'block' : (opt.value==''? 'block':'none'); });
            });

            function loadCountries() {
                fetch('https://restcountries.com/v3.1/all').then(r=>r.json()).then(list=>{
                    const names = list.map(c=>c.name.common).sort((a,b)=>a.localeCompare(b));
                    names.forEach(n=>{ const o=document.createElement('option'); o.value=n; o.textContent=n; countrySelect.appendChild(o); });
                    const kenyaOpt = Array.from(countrySelect.options).find(o=>o.value==='Kenya'); if (kenyaOpt) countrySelect.prepend(kenyaOpt);
                }).catch(()=>{
                    ['Kenya','Uganda','Tanzania','Rwanda','Nigeria','United States'].forEach(n=>{ const o=document.createElement('option'); o.value=n; o.textContent=n; countrySelect.appendChild(o); });
                });
            }

            countrySelect.addEventListener('change', function(){
                const v = this.value;
                otherWrapper.style.display = 'none'; manualLocation.value = '';
                kenyaCascade.style.display = 'none';
                resetBelow('county');
                if (!v) { locationHidden.value = ''; return; }
                if (v === 'Kenya') { kenyaCascade.style.display = 'block'; tryLoadKenyaData(); locationHidden.value = ''; }
                else { otherWrapper.style.display = 'block'; locationHidden.value = manualLocation.value || v; }
            });

            countySelect.addEventListener('change', function(){
                const sel = this.value;
                resetBelow('county');
                if (!sel) { locationHidden.value = ''; return; }
                if (sel === '__other__') { otherWrapper.style.display = 'block'; locationHidden.value = manualLocation.value; return; }
                otherWrapper.style.display = 'none'; manualLocation.value = '';
                const county = kenyaData.counties.find(c=>c.name===sel);
                if (!county) return;
                populateSelect(subcountySelect, county.subcounties || [], 'Select Sub-County');
            });

            subcountySelect.addEventListener('change', function(){
                const sel = this.value; resetBelow('subcounty'); if (!sel) { locationHidden.value = ''; return; }
                if (sel === '__other__') { otherWrapper.style.display = 'block'; locationHidden.value = manualLocation.value; return; }
                otherWrapper.style.display = 'none'; manualLocation.value = '';
                let sub = null;
                for (const c of kenyaData.counties) { sub = (c.subcounties||[]).find(s=>s.name===sel); if (sub) break; }
                if (!sub) return;
                populateSelect(divisionSelect, (sub.divisions||[]), 'Select Division / Ward');
            });

            divisionSelect.addEventListener('change', function(){
                const sel = this.value; resetBelow('division'); if (!sel) { locationHidden.value = ''; return; }
                if (sel === '__other__') { otherWrapper.style.display = 'block'; locationHidden.value = manualLocation.value; return; }
                otherWrapper.style.display = 'none'; manualLocation.value = '';
                let division = null;
                for (const c of kenyaData.counties) { for (const s of (c.subcounties||[])) { division = (s.divisions||[]).find(d=>d.name===sel); if (division) break; } if (division) break; }
                if (!division) return;
                populateSelect(locationSelect, (division.locations||[]), 'Select Location');
            });

            locationSelect.addEventListener('change', function(){
                const sel = this.value; resetBelow('location'); if (!sel) { locationHidden.value = ''; return; }
                if (sel === '__other__') { otherWrapper.style.display = 'block'; locationHidden.value = manualLocation.value; return; }
                otherWrapper.style.display = 'none'; manualLocation.value = '';
                let loc = null;
                for (const c of kenyaData.counties) { for (const s of (c.subcounties||[])) { for (const d of (s.divisions||[])) { loc = (d.locations||[]).find(l=>l.name===sel); if (loc) break; } if (loc) break; } if (loc) break; }
                if (!loc) return;
                const villages = loc.villages || [];
                populateSelect(villageSelect, villages, 'Select Village');
            });

            villageSelect.addEventListener('change', function(){
                const sel = this.value; if (!sel) { locationHidden.value = ''; return; }
                if (sel === '__other__') { otherWrapper.style.display = 'block'; locationHidden.value = manualLocation.value; return; }
                otherWrapper.style.display = 'none'; manualLocation.value = '';
                const parts = [];
                if (countySelect.value) parts.push(countySelect.value);
                if (subcountySelect.value) parts.push(subcountySelect.value);
                if (divisionSelect.value) parts.push(divisionSelect.value);
                if (locationSelect.value) parts.push(locationSelect.value);
                parts.push(sel);
                locationHidden.value = parts.join(' > ');
            });

            manualLocation.addEventListener('input', function(){ locationHidden.value = this.value; });

            document.getElementById('reportForm').addEventListener('submit', function(e) {
                const diseaseName = diseaseInput.value.trim();
                const symptoms = symptomsTextarea.value.trim();
                const finalLocation = locationHidden.value.trim();
                let isValid = true; let errorMessage = '';
                document.querySelectorAll('.form-control, .form-select').forEach(el=>el.style.borderColor='');
                if (!diseaseName) { diseaseInput.style.borderColor='#d32f2f'; isValid=false; errorMessage='Please enter a disease name.'; }
                if (!symptoms) { symptomsTextarea.style.borderColor='#d32f2f'; isValid=false; if (!errorMessage) errorMessage='Please describe the symptoms.'; }
                if (!finalLocation) { otherWrapper.style.display='block'; manualLocation.style.borderColor='#d32f2f'; isValid=false; if (!errorMessage) errorMessage='Please provide a location.'; }
                if (!isValid) { e.preventDefault(); alert('Please fix the following errors:\n\n' + errorMessage); }
            });

            loadCountries();
        });
    </script>
</body>
</html>