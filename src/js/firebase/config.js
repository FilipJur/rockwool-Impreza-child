// Import the functions you need from the SDKs you need
import { initializeApp } from "firebase/app";
// TODO: Add SDKs for Firebase products that you want to use
// https://firebase.google.com/docs/web/setup#available-libraries

// Your web app's Firebase configuration
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
