using Girasol.models;
using Microsoft.VisualBasic.FileIO;
using System;
using System.Collections.Generic;
using System.Globalization;
using System.Linq;
using System.Text;
using System.Text.RegularExpressions;
using System.Threading.Tasks;

namespace Girasol.Helpers
{
    public static class CsvTransFormData
    {
        private static ProtocoloFirma[] ParsearCsvPersonalizado(string csvContent, int modeRecibido, string rutaTsp, int ltv)
        {
            var registros = new List<ProtocoloFirma>();

            using var reader = new StringReader(csvContent);
            using var parser = new TextFieldParser(reader)
            {
                Delimiters = new[] { "," },
                HasFieldsEnclosedInQuotes = true,
                TrimWhiteSpace = true
            };
            int fila = 0;

            while (!parser.EndOfData)
            {
                fila++;

                var columnas = parser.ReadFields();
                if (columnas.Length < 3)
                    continue;


                bool visSigVisible = true;

                if (columnas.Length > 13 && bool.TryParse(columnas[13], out var visible))
                {
                    visSigVisible = visible;
                }

                int ParseRounded(string value, int defaultValue = 0)
                {
                    if (double.TryParse(value, NumberStyles.Any,
                                        CultureInfo.InvariantCulture, out var d))
                    {
                        return (int)Math.Round(d);
                    }
                    return defaultValue;
                }

                int x = ParseRounded(columnas.ElementAtOrDefault(3));
                int y = ParseRounded(columnas.ElementAtOrDefault(4));
                int page = int.TryParse(columnas.ElementAtOrDefault(9), out var tmpPage) ? tmpPage : 1;

                int width = int.TryParse(columnas.ElementAtOrDefault(5), out var w) ? w : 150;
                int height = int.TryParse(columnas.ElementAtOrDefault(6), out var h) ? h : 60;

                string text = columnas.ElementAtOrDefault(7)?.Replace("\\n", Environment.NewLine) ?? string.Empty;
                string? graphic = string.IsNullOrWhiteSpace(columnas.ElementAtOrDefault(8)) ? null : columnas[8];

                int textSize = int.TryParse(columnas.ElementAtOrDefault(10), out var size) ? size : 10;
                int rotation = int.TryParse(columnas.ElementAtOrDefault(12), out var rot) ? rot : 0;

                // Columna 11: SHA-256 del PDF (para verificación de integridad)
                string pdfSha256 = columnas.ElementAtOrDefault(11)?.Trim() ?? string.Empty;

                var item = new ProtocoloFirma
                {
                    From = columnas[0],
                    To = columnas[1],
                    NamePdf = columnas[2],
                    VisSigX = x,
                    VisSigY = y,
                    VisSigWidth = width,
                    VisSigHeight = height,
                    VisSigText = visSigVisible ? text : string.Empty,
                    VisSigGraphic = graphic,
                    VisSigPage = page,
                    VisSigTextSize = textSize,
                    VisSigRotation = rotation,
                    PdfSha256 = pdfSha256,
                    Tsp = rutaTsp,
                    Tlv = ltv,
                    UploadSimple = true,
                    RutaImagenLogo = string.Empty,
                    VisSigVisible = visSigVisible
                };

                if (!visSigVisible)
                {
                    item.Mode = 0;

                    item.VisSigX = 0;
                    item.VisSigY = 0;
                    item.VisSigWidth = 0;
                    item.VisSigHeight = 0;
                    item.VisSigText = string.Empty;
                    item.VisSigGraphic = null;
                }
                else
                {
                    item.Mode = InferirModoFinal(modeRecibido, item);
                }

                registros.Add(item);
            }

            return registros.ToArray();
        }

        public static ProtocoloFirma[] LeerArchivoCsv(string rutaArchivo, int mode, string rutaTsp, int ltv)
        {
            if (!File.Exists(rutaArchivo))
            {
                throw new FileNotFoundException($"No se encontró el archivo CSV en la ruta: {rutaArchivo}");
            }

            var csvContent = File.ReadAllText(rutaArchivo);
            return ParsearCsvPersonalizado(csvContent, mode, rutaTsp, ltv);
        }

        private static int InferirModoFinal(int modeRecibido, ProtocoloFirma item)
        {
            bool tienePosicion = item.VisSigX > 0 && item.VisSigY > 0;

            if (!tienePosicion)
                return 1;

            if (modeRecibido == 1)
                return 1;
            return 0;
        }
    }
}
