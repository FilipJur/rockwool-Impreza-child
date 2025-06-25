/**
 * Firebase Configuration
 * 
 * Initializes Firebase app for authentication and messaging services.
 * Used for:
 * - SMS OTP authentication during user registration
 * - Future SMS notifications system
 * 
 * @package mistr-fachman
 * @since 1.0.0
 */

import { initializeApp } from "firebase/app";

// Firebase project configuration
const firebaseConfig = {
  apiKey: "AIzaSyC4M_V1sb-lwPc_UYF7gqeyoiTa0sFXH5w",
  authDomain: "mistrfachman.firebaseapp.com",
  projectId: "mistrfachman",
  storageBucket: "mistrfachman.firebasestorage.app",
  messagingSenderId: "52606122618",
  appId: "1:52606122618:web:930390a09119d096ff99c3"
};

// Initialize Firebase
const app = initializeApp(firebaseConfig);

export { app, firebaseConfig };
