<?php
require_once __DIR__ . '/../../common/constants.php';
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../layouts/website/page-template.php';

$pageTitle = 'Digital Wallet Policies & Documentation';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? ''); ?> | GCA</title>
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/assets/images/logogmn.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/gm-public.css">
    
</head>

<body class="bg-gray-50 text-gray-800">
    <?php include __DIR__ . '/../../include/public-header.php'; ?>

    <!-- Hero Section -->
    <section class="bg-blue-600 py-16 md:py-24">
        <div class="container mx-auto px-4 text-center">
            <h1 class="text-4xl md:text-5xl font-extrabold text-white mb-4">Digital Wallet Policies</h1>
            <p class="text-blue-100 text-lg max-w-2xl mx-auto">Comprehensive policies, terms, and conditions for the GCA Digital Wallet system.</p>
        </div>
    </section>

    <section class="container mx-auto px-4 py-12 md:py-16">
        <div class="flex flex-col lg:flex-row gap-12">
            <!-- Sidebar Navigation -->
            <aside class="lg:w-1/4">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 sticky top-24">
                    <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-6">On this page</h3>
                    <nav class="space-y-4">
                        <a href="#cancellation" class="block text-gray-600 hover:text-blue-600 font-medium transition-colors">1. Cancellation & Refund</a>
                        <a href="#terms" class="block text-gray-600 hover:text-blue-600 font-medium transition-colors">2. Terms & Conditions</a>
                        <a href="#about" class="block text-gray-600 hover:text-blue-600 font-medium transition-colors">3. About Us</a>
                        <a href="#contact" class="block text-gray-600 hover:text-blue-600 font-medium transition-colors">4. Contact Us</a>
                        <a href="#legal" class="block text-gray-600 hover:text-blue-600 font-medium transition-colors">5. Governing Law</a>
                        <a href="#business" class="block text-gray-600 hover:text-blue-600 font-medium transition-colors">6. Business Details</a>
                    </nav>
                </div>
            </aside>

            <!-- Main Content -->
            <div class="lg:w-3/4">
                <div class="bg-white p-8 md:p-12 rounded-3xl shadow-xl border border-gray-100 prose prose-blue max-w-none">
                    
                    <p class="text-gray-500 italic mb-10">Last Revised: March 23, 2026</p>

                    <div id="cancellation" class="policy-section mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 mb-6 flex items-center">
                            <span class="bg-blue-100 text-blue-600 w-10 h-10 rounded-lg flex items-center justify-center mr-4 text-xl">1</span>
                            Cancellation and Refund Policy
                        </h2>
                        <p class="text-gray-600 leading-relaxed mb-6">Gyanmanjari Career Academy (GCA) aims to provide a seamless digital payment experience. Our refund and cancellation policy is designed to be fair and transparent.</p>
                        
                        <h3 class="text-xl font-bold text-gray-800 mb-4">1.1 Wallet Top-ups and Deposits</h3>
                        <ul class="list-disc pl-6 space-y-3 text-gray-600 mb-6">
                            <li><strong>Finality of Transactions:</strong> All funds added to the GCA Digital Wallet are considered final. Once a top-up is successfully processed, the amount is non-refundable and non-transferable to any external bank account.</li>
                            <li><strong>Usage:</strong> The balance in the digital wallet can only be utilized for the payment of Grossery store, Sallon, Laundry and Wending Machine. <strong>But not</strong> institutional fees, Tution Fees and Trasports Fees, Hostel charges and examination fees within the GCA ecosystem.</li>
                        </ul>

                        <h3 class="text-xl font-bold text-gray-800 mb-4">1.2 Transaction Failures and Errors</h3>
                        <ul class="list-disc pl-6 space-y-3 text-gray-600 mb-6">
                            <li><strong>Failed Transactions:</strong> If an amount is debited from your bank account but not reflected in the GCA Digital Wallet due to a technical error, the amount is usually refunded automatically by your bank within 5-7 working days.</li>
                            <li><strong>Duplicate Payments:</strong> In case of a verified duplicate transaction (where the same amount is charged twice for the same top-up request), GCA will initiate a refund of the excess amount to the original payment source within 7-10 working days after a formal request is raised.</li>
                        </ul>

                        <h3 class="text-xl font-bold text-gray-800 mb-4">1.3 Offence and Unfair Activities</h3>
                        <p class="text-gray-600 leading-relaxed mb-6">Hacking and unfair activities regarding digital wallet is statutory offence. It may cause the issue of Debarment from institute.</p>

                        <h3 class="text-xl font-bold text-gray-800 mb-4">1.4 Accidental Top-ups</h3>
                        <p class="text-gray-600 leading-relaxed mb-6">GCA is not responsible for accidental top-ups made by the user. Users are advised to verify the amount before confirming the transaction. However, if a significant error occurs, users may contact the GCA support desk for a manual review, though refunds are not guaranteed.</p>
                    </div>

                    <hr class="my-10 border-gray-100">

                    <div id="terms" class="policy-section mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 mb-6 flex items-center">
                            <span class="bg-blue-100 text-blue-600 w-10 h-10 rounded-lg flex items-center justify-center mr-4 text-xl">2</span>
                            Terms and Conditions
                        </h2>
                        <p class="text-gray-600 mb-6">By using the GCA Digital Wallet, you agree to the following terms:</p>

                        <h3 class="text-xl font-bold text-gray-800 mb-4">2.1 User Responsibility and Security</h3>
                        <ul class="list-disc pl-6 space-y-3 text-gray-600 mb-6">
                            <li><strong>Account Security:</strong> You are solely responsible for maintaining the confidentiality of your login credentials (username, password, and transaction PIN). GCA will not be liable for any loss arising from unauthorized access due to your negligence.</li>
                            <li><strong>Accurate Information:</strong> You must ensure that all personal and financial details provided during registration and wallet usage are accurate and up-to-date.</li>
                        </ul>

                        <h3 class="text-xl font-bold text-gray-800 mb-4">2.2 Prohibited Activities</h3>
                        <ul class="list-disc pl-6 space-y-3 text-gray-600 mb-6">
                            <li>Users shall not use the GCA Digital Wallet for any fraudulent, illegal, or unauthorized purposes.</li>
                            <li>Any attempt to bypass security protocols, reverse engineer the system, or exploit technical vulnerabilities will result in immediate account suspension and potential legal action.</li>
                        </ul>

                        <h3 class="text-xl font-bold text-gray-800 mb-4">2.3 System Modifications</h3>
                        <ul class="list-disc pl-6 space-y-3 text-gray-600 mb-6">
                            <li>GCA reserves all the right to modify, suspend, or discontinue any feature of the Digital Wallet system at any time without prior notice.</li>
                            <li>Fees for transactions or wallet maintenance, if any, may be introduced or changed by GCA with 30 days’ prior notice on the official website.</li>
                        </ul>

                        <h3 class="text-xl font-bold text-gray-800 mb-4">2.4 Limitation of Liability</h3>
                        <p class="text-gray-600 leading-relaxed mb-6">GCA shall not be liable for any indirect, incidental, or consequential damages arising out of the use or inability to use the Digital Wallet service, including but not limited to loss of data or financial loss due to third-party payment gateway failures.</p>
                    </div>

                    <hr class="my-10 border-gray-100">

                    <div id="about" class="policy-section mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 mb-6 flex items-center">
                            <span class="bg-blue-100 text-blue-600 w-10 h-10 rounded-lg flex items-center justify-center mr-4 text-xl">3</span>
                            About Us
                        </h2>
                        <p class="text-gray-600 leading-relaxed mb-6">Gyanmanjari Career Academy (GCA) is a flagship educational venture of Gyanmanjari Vidyapith. Established with a vision to redefine career-oriented education, GCA provides a structured environment for students to excel in competitive exams and academic pursuits.</p>
                        
                        <div class="grid md:grid-cols-2 gap-8 mt-8">
                            <div class="bg-blue-50 p-6 rounded-2xl">
                                <h4 class="font-bold text-blue-700 mb-2">Our Mission</h4>
                                <p class="text-sm text-gray-600">To empower students with high-quality education, personalized counselling, and state-of-the-art digital infrastructure that simplifies their administrative and academic journey.</p>
                            </div>
                            <div class="bg-indigo-50 p-6 rounded-2xl">
                                <h4 class="font-bold text-indigo-700 mb-2">Our Values</h4>
                                <ul class="text-sm text-gray-600 space-y-1">
                                    <li>• <strong>Innovation:</strong> Leveraging technology systems like Digital Wallet.</li>
                                    <li>• <strong>Transparency:</strong> Clear communication on fees and policies.</li>
                                    <li>• <strong>Excellence:</strong> Highest standards in student support.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <hr class="my-10 border-gray-100">

                    <div id="contact" class="policy-section mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 mb-6 flex items-center">
                            <span class="bg-blue-100 text-blue-600 w-10 h-10 rounded-lg flex items-center justify-center mr-4 text-xl">4</span>
                            Contact Us
                        </h2>
                        <p class="text-gray-600 mb-6">We are here to help you. For any assistance regarding your digital wallet, payments, or general queries, please reach out to us:</p>
                        
                        <div class="bg-gray-50 p-6 rounded-2xl border border-gray-100">
                            <ul class="space-y-4">
                                <li class="flex items-start">
                                    <i class="fas fa-envelope text-blue-600 mt-1 mr-4"></i>
                                    <div>
                                        <p class="font-bold text-gray-800">Email Support</p>
                                        <p class="text-gray-600">support@gyanmanjari.co.in (Support)</p>
                                        <p class="text-gray-600">info@gyanmanjari.co.in (Admin)</p>
                                    </div>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-clock text-blue-600 mt-1 mr-4"></i>
                                    <div>
                                        <p class="font-bold text-gray-800">Support Hours</p>
                                        <p class="text-gray-600">Monday to Saturday, 9:00 AM to 5:00 PM (IST)</p>
                                    </div>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-building text-blue-600 mt-1 mr-4"></i>
                                    <div>
                                        <p class="font-bold text-gray-800">Physical Help Desk</p>
                                        <p class="text-gray-600">Visit the administrative office at the GCA campus.</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <hr class="my-10 border-gray-100">

                    <div id="legal" class="policy-section mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 mb-6 flex items-center">
                            <span class="bg-blue-100 text-blue-600 w-10 h-10 rounded-lg flex items-center justify-center mr-4 text-xl">5</span>
                            Governing Law and Dispute Resolution
                        </h2>
                        <h3 class="text-xl font-bold text-gray-800 mb-4">5.1 Legal Jurisdiction</h3>
                        <p class="text-gray-600 leading-relaxed mb-6">These terms and conditions are governed by and construed in accordance with the Laws of India. The application of the laws of any other country is expressly excluded.</p>
                        
                        <h3 class="text-xl font-bold text-gray-800 mb-4">5.2 Dispute Resolution</h3>
                        <ul class="list-disc pl-6 space-y-3 text-gray-600">
                            <li><strong>Internal Resolution:</strong> Any dispute, controversy, or claim shall first be attempted to be settled through mutual discussion between the user and the GCA administration.</li>
                            <li><strong>Legal Action:</strong> If the parties fail to reach an amicable settlement within 30 days, the dispute shall be subject to the exclusive jurisdiction of the competent courts in Bhavnagar, Gujarat, India.</li>
                            <li><strong>State Regulations:</strong> These terms are also subject to the specific regulations of the State Government of Gujarat as applicable to educational institutions and digital transactions.</li>
                        </ul>
                    </div>

                    <hr class="my-10 border-gray-100">

                    <div id="business" class="policy-section mb-0">
                        <h2 class="text-3xl font-bold text-gray-900 mb-6 flex items-center">
                            <span class="bg-blue-100 text-blue-600 w-10 h-10 rounded-lg flex items-center justify-center mr-4 text-xl">6</span>
                            Registered Business Details
                        </h2>
                        <p class="text-gray-600 mb-6">Gyanmanjari Career Academy operates as a GST-registered entity. All financial transactions are processed under the following registered details:</p>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <tbody>
                                    <tr class="border-b border-gray-100">
                                        <th class="py-4 font-bold text-gray-800 w-1/3">Legal Name</th>
                                        <td class="py-4 text-gray-600">GYANMANJARI CAREER ACADEMY</td>
                                    </tr>
                                    <tr class="border-b border-gray-100">
                                        <th class="py-4 font-bold text-gray-800">GSTIN</th>
                                        <td class="py-4 text-gray-600 font-mono">24AASFG3028N1Z1</td>
                                    </tr>
                                    <tr class="border-b border-gray-100">
                                        <th class="py-4 font-bold text-gray-800">PAN</th>
                                        <td class="py-4 text-gray-600 font-mono">AASFG3028N</td>
                                    </tr>
                                    <tr class="border-b border-gray-100">
                                        <th class="py-4 font-bold text-gray-800">Principal Place of Business</th>
                                        <td class="py-4 text-gray-600">Sartanpar, Ghogha, Bhavnagar, Gujarat - 364002</td>
                                    </tr>
                                    <tr>
                                        <th class="py-4 font-bold text-gray-800">Official Website</th>
                                        <td class="py-4 text-blue-600">www.gyanmanjari.com</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/../../include/public-footer.php'; ?>
</body>

</html>
