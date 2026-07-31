using System;
using System.IO;
using System.Security.Cryptography;
using System.Text;

// Consumo: verificacion offline de URIs firmaEasy con Ed25519
// Target: .NET MAUI (Android / iOS / Windows) | net8.0+
// Requisito: clave publica embebida o cargada desde archivo local

const string exampleUri = "firmeasy:?from=https%3A%2F%2Fexample.com%2Ffrom&to=https%3A%2F%2Fexample.com%2Fto&vis_sig_x=79&vis_sig_page=1&doc_sha256=abc123def&exp=1753372800&nonce=a1b2c3d4e5f6g7h8&kid=test-key-1&sig=OY5GFpmlQVnu2zlzmeefraJMJTigrq1DnYwTJgQ9_HBr8ghIhn0c4gMOQ_O5jTjzzt1GJv-dnz2-7-_C8IaoBw";

const string publicKeyPem = """
-----BEGIN PUBLIC KEY-----
MCowBQYDK2VwAyEAaRHnXE/8z9YsiOUaePOu2ieAP5tSMvYdfrW3hNj1ta0=
-----END PUBLIC KEY-----
""";

var uri = exampleUri;

if (!uri.StartsWith("firmeasy:?") || !uri.Contains("&sig="))
{
    Console.WriteLine("URI invalida: falta prefijo o firma");
    return;
}

var body = uri["firmeasy:?".Length..];
var sigIdx = body.LastIndexOf("&sig=");
if (sigIdx == -1)
{
    Console.WriteLine("URI invalida: falta sig");
    return;
}

var signedPart = body[..sigIdx];
var sigB64 = body[(sigIdx + "&sig=".Length)..];

var kid = ExtractKid(signedPart);

Console.WriteLine($"kid detectado: {kid}");
Console.WriteLine($"Firma (base64url): {sigB64}");
Console.WriteLine($"Bytes firmados ({signedPart.Length} chars)");
Console.WriteLine();

var dataBytes = Encoding.UTF8.GetBytes(signedPart);
var sigBytes = Base64UrlDecode(sigB64);
var derBytes = PemToDer(publicKeyPem);

using var publicKey = Ed25519PublicKey.ImportSubjectPublicKeyInfo(derBytes, out _);
var valid = Ed25519.Verify(dataBytes, sigBytes, publicKey);

Console.WriteLine($"Firma valida: {valid}");
Console.WriteLine("=== Verificacion completa ===");
Console.WriteLine($"1. URI extraida correctamente");
Console.WriteLine($"2. Clave publica cargada para kid: {kid}");
Console.WriteLine($"3. Cadena firmada reconstruida ({dataBytes.Length} bytes UTF-8)");
Console.WriteLine($"4. Firma decodificada de base64url ({sigBytes.Length} bytes)");
Console.WriteLine($"5. Ed25519.Verify() devolvio: {valid}");

static string ExtractKid(string signedPart)
{
    var kidIdx = signedPart.IndexOf("&kid=");
    if (kidIdx == -1) return "(sin kid)";
    var rest = signedPart[(kidIdx + "&kid=".Length)..];
    var ampIdx = rest.IndexOf('&');
    var raw = ampIdx != -1 ? rest[..ampIdx] : rest;
    return Uri.UnescapeDataString(raw);
}

static byte[] PemToDer(string pem)
{
    var sb = new StringBuilder();
    foreach (var line in pem.Split('\n'))
    {
        var t = line.Trim();
        if (t.StartsWith("-----") || t.Length == 0) continue;
        sb.Append(t);
    }
    return Convert.FromBase64String(sb.ToString());
}

static byte[] Base64UrlDecode(string input)
{
    var pad = input.Length % 4;
    var padded = pad switch
    {
        2 => input + "==",
        3 => input + "=",
        _ => input
    };
    return Convert.FromBase64String(padded.Replace('-', '+').Replace('_', '/'));
}