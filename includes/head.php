<?php
// Function to get the current page name for active states
function getCurrentPage() {
    return basename($_SERVER['PHP_SELF']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CB FINANCE | Professional Financial Solutions</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            green: '#00A651',
                            blue: '#003366',
                            light: '#00BFFF',
                        },
                        neutral: {
                            bg: '#F5F7FA',
                            card: '#FFFFFF',
                            text: '#333333',
                            heading: '#1A1F2E',
                        },
                        accent: {
                            gold: '#D4AF37',
                            teal: '#26A69A',
                        }
                    },
                    fontFamily: {
                        outfit: ['Outfit', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #F5F7FA;
            color: #333333;
            overflow-x: hidden;
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        .gradient-blue {
            background: linear-gradient(135deg, #003366 0%, #0047AB 100%);
        }
        .gradient-green {
            background: linear-gradient(135deg, #008000 0%, #00A651 100%);
        }
        .nav-transition {
            transition: all 0.3s ease;
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        ::-webkit-scrollbar-thumb {
            background: #003366;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #00A651;
        }
        
        /* Mobile Bottom Nav Padding */
        @media (max-width: 768px) {
            body {
                padding-bottom: 70px;
            }
        }
        
        .step-active {
            color: #00A651;
            border-color: #00A651;
        }
        .step-completed {
            background-color: #00A651;
            color: white;
            border-color: #00A651;
        }
    </style>
</head>
<body class="bg-neutral-bg">
