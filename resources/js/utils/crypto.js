// utils/crypto.js
import CryptoJS from 'crypto-js';

const SECRET_KEY = 'N0eL1SmYN@mE'; // Replace with a secure key

export function encrypt(data) {
  return CryptoJS.AES.encrypt(JSON.stringify(data), SECRET_KEY).toString();
}

export function decrypt(ciphertext) {
  const bytes = CryptoJS.AES.decrypt(ciphertext, SECRET_KEY);
  const decryptedData = bytes.toString(CryptoJS.enc.Utf8);
  try {
    return JSON.parse(decryptedData);
  } catch (e) {
    return null;
  }
}
