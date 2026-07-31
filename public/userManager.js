class UserManager {
  static getUserId() {
    // Para testing usamos testuser fijo; en producción usar localStorage
    return "testuser";
  }

  static getUserName() {
    return (
      localStorage.getItem("demoUserName") ||
      `Usuario ${this.getUserId().substring(5)}`
    );
  }

  static setUserName(name) {
    localStorage.setItem("demoUserName", name);
  }
}

// signatureTracker.js
class SignatureTracker {
  static documentWasSignedByUser(documentId) {
    const signatures = JSON.parse(localStorage.getItem('documentSignatures') || {});
    return !!signatures[documentId];
  }

  static recordSignature(documentId, signatureData) {
    const signatures = JSON.parse(localStorage.getItem('documentSignatures') || '{}');
    signatures[documentId] = {
      timestamp: new Date().toISOString(),
      userId: UserManager.getUserId(),
      userName: UserManager.getUserName(),
      ...signatureData
    };
    localStorage.setItem('documentSignatures', JSON.stringify(signatures));
  }

  static getUserSignatures() {
    return JSON.parse(localStorage.getItem('documentSignatures') || '{}');
  }
}

// Función para reiniciar la sesión (limpiar localStorage + servidor y recargar)
async function resetApp() {
    try {
        await fetch('api.php?op=reset', { method: 'POST' });
    } catch (e) {}
    localStorage.clear();
    location.reload();
}