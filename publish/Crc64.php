<?php

declare(strict_types=1);

/**
 * CRC-64-XZ
 *
 * x^64 + x^62 + x^57 + x^55 + x^54 + x^53 + x^52 + x^47 + x^46 + x^45 + x^40 +
 * x^39 + x^38 + x^37 + x^35 + x^33 + x^32 + x^31 + x^29 + x^27 + x^24 + x^23 +
 * x^22 + x^21 + x^19 + x^17 + x^13 + x^12 + x^10 + x^9 + x^7 + x^4 + x + 1
 *
 * Polynomial = 0x42F0E1EBA9EA3693
 * Initial    = 0xFFFFFFFFFFFFFFFF
 * Final XOR  = 0xFFFFFFFFFFFFFFFF
 * Reflect    = true
 */
final class Crc64
{
    /**
     * The precomputed polynomial.
     *
     * Some 64-bit unsigned integers exceed PHP_INT_MAX, causing PHP to parse
     * them as floats. To bypass this limitation, we represent integers as
     * high and low bytes and construct them together at runtime.
     *
     * @var int[]
     */
    private const TABLE = [
        0x00000000 << 32 | 0x00000000, 0x42F0E1EB << 32 | 0xA9EA3693,
        0x85E1C3D7 << 32 | 0x53D46D26, 0xC711223C << 32 | 0xFA3E5BB5,
        0x49336645 << 32 | 0x0E42ECDF, 0x0BC387AE << 32 | 0xA7A8DA4C,
        0xCCD2A592 << 32 | 0x5D9681F9, 0x8E224479 << 32 | 0xF47CB76A,
        0x9266CC8A << 32 | 0x1C85D9BE, 0xD0962D61 << 32 | 0xB56FEF2D,
        0x17870F5D << 32 | 0x4F51B498, 0x5577EEB6 << 32 | 0xE6BB820B,
        0xDB55AACF << 32 | 0x12C73561, 0x99A54B24 << 32 | 0xBB2D03F2,
        0x5EB46918 << 32 | 0x41135847, 0x1C4488F3 << 32 | 0xE8F96ED4,
        0x663D78FF << 32 | 0x90E185EF, 0x24CD9914 << 32 | 0x390BB37C,
        0xE3DCBB28 << 32 | 0xC335E8C9, 0xA12C5AC3 << 32 | 0x6ADFDE5A,
        0x2F0E1EBA << 32 | 0x9EA36930, 0x6DFEFF51 << 32 | 0x37495FA3,
        0xAAEFDD6D << 32 | 0xCD770416, 0xE81F3C86 << 32 | 0x649D3285,
        0xF45BB475 << 32 | 0x8C645C51, 0xB6AB559E << 32 | 0x258E6AC2,
        0x71BA77A2 << 32 | 0xDFB03177, 0x334A9649 << 32 | 0x765A07E4,
        0xBD68D230 << 32 | 0x8226B08E, 0xFF9833DB << 32 | 0x2BCC861D,
        0x388911E7 << 32 | 0xD1F2DDA8, 0x7A79F00C << 32 | 0x7818EB3B,
        0xCC7AF1FF << 32 | 0x21C30BDE, 0x8E8A1014 << 32 | 0x88293D4D,
        0x499B3228 << 32 | 0x721766F8, 0x0B6BD3C3 << 32 | 0xDBFD506B,
        0x854997BA << 32 | 0x2F81E701, 0xC7B97651 << 32 | 0x866BD192,
        0x00A8546D << 32 | 0x7C558A27, 0x4258B586 << 32 | 0xD5BFBCB4,
        0x5E1C3D75 << 32 | 0x3D46D260, 0x1CECDC9E << 32 | 0x94ACE4F3,
        0xDBFDFEA2 << 32 | 0x6E92BF46, 0x990D1F49 << 32 | 0xC77889D5,
        0x172F5B30 << 32 | 0x33043EBF, 0x55DFBADB << 32 | 0x9AEE082C,
        0x92CE98E7 << 32 | 0x60D05399, 0xD03E790C << 32 | 0xC93A650A,
        0xAA478900 << 32 | 0xB1228E31, 0xE8B768EB << 32 | 0x18C8B8A2,
        0x2FA64AD7 << 32 | 0xE2F6E317, 0x6D56AB3C << 32 | 0x4B1CD584,
        0xE374EF45 << 32 | 0xBF6062EE, 0xA1840EAE << 32 | 0x168A547D,
        0x66952C92 << 32 | 0xECB40FC8, 0x2465CD79 << 32 | 0x455E395B,
        0x3821458A << 32 | 0xADA7578F, 0x7AD1A461 << 32 | 0x044D611C,
        0xBDC0865D << 32 | 0xFE733AA9, 0xFF3067B6 << 32 | 0x57990C3A,
        0x711223CF << 32 | 0xA3E5BB50, 0x33E2C224 << 32 | 0x0A0F8DC3,
        0xF4F3E018 << 32 | 0xF031D676, 0xB60301F3 << 32 | 0x59DBE0E5,
        0xDA050215 << 32 | 0xEA6C212F, 0x98F5E3FE << 32 | 0x438617BC,
        0x5FE4C1C2 << 32 | 0xB9B84C09, 0x1D142029 << 32 | 0x10527A9A,
        0x93366450 << 32 | 0xE42ECDF0, 0xD1C685BB << 32 | 0x4DC4FB63,
        0x16D7A787 << 32 | 0xB7FAA0D6, 0x5427466C << 32 | 0x1E109645,
        0x4863CE9F << 32 | 0xF6E9F891, 0x0A932F74 << 32 | 0x5F03CE02,
        0xCD820D48 << 32 | 0xA53D95B7, 0x8F72ECA3 << 32 | 0x0CD7A324,
        0x0150A8DA << 32 | 0xF8AB144E, 0x43A04931 << 32 | 0x514122DD,
        0x84B16B0D << 32 | 0xAB7F7968, 0xC6418AE6 << 32 | 0x02954FFB,
        0xBC387AEA << 32 | 0x7A8DA4C0, 0xFEC89B01 << 32 | 0xD3679253,
        0x39D9B93D << 32 | 0x2959C9E6, 0x7B2958D6 << 32 | 0x80B3FF75,
        0xF50B1CAF << 32 | 0x74CF481F, 0xB7FBFD44 << 32 | 0xDD257E8C,
        0x70EADF78 << 32 | 0x271B2539, 0x321A3E93 << 32 | 0x8EF113AA,
        0x2E5EB660 << 32 | 0x66087D7E, 0x6CAE578B << 32 | 0xCFE24BED,
        0xABBF75B7 << 32 | 0x35DC1058, 0xE94F945C << 32 | 0x9C3626CB,
        0x676DD025 << 32 | 0x684A91A1, 0x259D31CE << 32 | 0xC1A0A732,
        0xE28C13F2 << 32 | 0x3B9EFC87, 0xA07CF219 << 32 | 0x9274CA14,
        0x167FF3EA << 32 | 0xCBAF2AF1, 0x548F1201 << 32 | 0x62451C62,
        0x939E303D << 32 | 0x987B47D7, 0xD16ED1D6 << 32 | 0x31917144,
        0x5F4C95AF << 32 | 0xC5EDC62E, 0x1DBC7444 << 32 | 0x6C07F0BD,
        0xDAAD5678 << 32 | 0x9639AB08, 0x985DB793 << 32 | 0x3FD39D9B,
        0x84193F60 << 32 | 0xD72AF34F, 0xC6E9DE8B << 32 | 0x7EC0C5DC,
        0x01F8FCB7 << 32 | 0x84FE9E69, 0x43081D5C << 32 | 0x2D14A8FA,
        0xCD2A5925 << 32 | 0xD9681F90, 0x8FDAB8CE << 32 | 0x70822903,
        0x48CB9AF2 << 32 | 0x8ABC72B6, 0x0A3B7B19 << 32 | 0x23564425,
        0x70428B15 << 32 | 0x5B4EAF1E, 0x32B26AFE << 32 | 0xF2A4998D,
        0xF5A348C2 << 32 | 0x089AC238, 0xB753A929 << 32 | 0xA170F4AB,
        0x3971ED50 << 32 | 0x550C43C1, 0x7B810CBB << 32 | 0xFCE67552,
        0xBC902E87 << 32 | 0x06D82EE7, 0xFE60CF6C << 32 | 0xAF321874,
        0xE224479F << 32 | 0x47CB76A0, 0xA0D4A674 << 32 | 0xEE214033,
        0x67C58448 << 32 | 0x141F1B86, 0x253565A3 << 32 | 0xBDF52D15,
        0xAB1721DA << 32 | 0x49899A7F, 0xE9E7C031 << 32 | 0xE063ACEC,
        0x2EF6E20D << 32 | 0x1A5DF759, 0x6C0603E6 << 32 | 0xB3B7C1CA,
        0xF6FAE5C0 << 32 | 0x7D3274CD, 0xB40A042B << 32 | 0xD4D8425E,
        0x731B2617 << 32 | 0x2EE619EB, 0x31EBC7FC << 32 | 0x870C2F78,
        0xBFC98385 << 32 | 0x73709812, 0xFD39626E << 32 | 0xDA9AAE81,
        0x3A284052 << 32 | 0x20A4F534, 0x78D8A1B9 << 32 | 0x894EC3A7,
        0x649C294A << 32 | 0x61B7AD73, 0x266CC8A1 << 32 | 0xC85D9BE0,
        0xE17DEA9D << 32 | 0x3263C055, 0xA38D0B76 << 32 | 0x9B89F6C6,
        0x2DAF4F0F << 32 | 0x6FF541AC, 0x6F5FAEE4 << 32 | 0xC61F773F,
        0xA84E8CD8 << 32 | 0x3C212C8A, 0xEABE6D33 << 32 | 0x95CB1A19,
        0x90C79D3F << 32 | 0xEDD3F122, 0xD2377CD4 << 32 | 0x4439C7B1,
        0x15265EE8 << 32 | 0xBE079C04, 0x57D6BF03 << 32 | 0x17EDAA97,
        0xD9F4FB7A << 32 | 0xE3911DFD, 0x9B041A91 << 32 | 0x4A7B2B6E,
        0x5C1538AD << 32 | 0xB04570DB, 0x1EE5D946 << 32 | 0x19AF4648,
        0x02A151B5 << 32 | 0xF156289C, 0x4051B05E << 32 | 0x58BC1E0F,
        0x87409262 << 32 | 0xA28245BA, 0xC5B07389 << 32 | 0x0B687329,
        0x4B9237F0 << 32 | 0xFF14C443, 0x0962D61B << 32 | 0x56FEF2D0,
        0xCE73F427 << 32 | 0xACC0A965, 0x8C8315CC << 32 | 0x052A9FF6,
        0x3A80143F << 32 | 0x5CF17F13, 0x7870F5D4 << 32 | 0xF51B4980,
        0xBF61D7E8 << 32 | 0x0F251235, 0xFD913603 << 32 | 0xA6CF24A6,
        0x73B3727A << 32 | 0x52B393CC, 0x31439391 << 32 | 0xFB59A55F,
        0xF652B1AD << 32 | 0x0167FEEA, 0xB4A25046 << 32 | 0xA88DC879,
        0xA8E6D8B5 << 32 | 0x4074A6AD, 0xEA16395E << 32 | 0xE99E903E,
        0x2D071B62 << 32 | 0x13A0CB8B, 0x6FF7FA89 << 32 | 0xBA4AFD18,
        0xE1D5BEF0 << 32 | 0x4E364A72, 0xA3255F1B << 32 | 0xE7DC7CE1,
        0x64347D27 << 32 | 0x1DE22754, 0x26C49CCC << 32 | 0xB40811C7,
        0x5CBD6CC0 << 32 | 0xCC10FAFC, 0x1E4D8D2B << 32 | 0x65FACC6F,
        0xD95CAF17 << 32 | 0x9FC497DA, 0x9BAC4EFC << 32 | 0x362EA149,
        0x158E0A85 << 32 | 0xC2521623, 0x577EEB6E << 32 | 0x6BB820B0,
        0x906FC952 << 32 | 0x91867B05, 0xD29F28B9 << 32 | 0x386C4D96,
        0xCEDBA04A << 32 | 0xD0952342, 0x8C2B41A1 << 32 | 0x797F15D1,
        0x4B3A639D << 32 | 0x83414E64, 0x09CA8276 << 32 | 0x2AAB78F7,
        0x87E8C60F << 32 | 0xDED7CF9D, 0xC51827E4 << 32 | 0x773DF90E,
        0x020905D8 << 32 | 0x8D03A2BB, 0x40F9E433 << 32 | 0x24E99428,
        0x2CFFE7D5 << 32 | 0x975E55E2, 0x6E0F063E << 32 | 0x3EB46371,
        0xA91E2402 << 32 | 0xC48A38C4, 0xEBEEC5E9 << 32 | 0x6D600E57,
        0x65CC8190 << 32 | 0x991CB93D, 0x273C607B << 32 | 0x30F68FAE,
        0xE02D4247 << 32 | 0xCAC8D41B, 0xA2DDA3AC << 32 | 0x6322E288,
        0xBE992B5F << 32 | 0x8BDB8C5C, 0xFC69CAB4 << 32 | 0x2231BACF,
        0x3B78E888 << 32 | 0xD80FE17A, 0x79880963 << 32 | 0x71E5D7E9,
        0xF7AA4D1A << 32 | 0x85996083, 0xB55AACF1 << 32 | 0x2C735610,
        0x724B8ECD << 32 | 0xD64D0DA5, 0x30BB6F26 << 32 | 0x7FA73B36,
        0x4AC29F2A << 32 | 0x07BFD00D, 0x08327EC1 << 32 | 0xAE55E69E,
        0xCF235CFD << 32 | 0x546BBD2B, 0x8DD3BD16 << 32 | 0xFD818BB8,
        0x03F1F96F << 32 | 0x09FD3CD2, 0x41011884 << 32 | 0xA0170A41,
        0x86103AB8 << 32 | 0x5A2951F4, 0xC4E0DB53 << 32 | 0xF3C36767,
        0xD8A453A0 << 32 | 0x1B3A09B3, 0x9A54B24B << 32 | 0xB2D03F20,
        0x5D459077 << 32 | 0x48EE6495, 0x1FB5719C << 32 | 0xE1045206,
        0x919735E5 << 32 | 0x1578E56C, 0xD367D40E << 32 | 0xBC92D3FF,
        0x1476F632 << 32 | 0x46AC884A, 0x568617D9 << 32 | 0xEF46BED9,
        0xE085162A << 32 | 0xB69D5E3C, 0xA275F7C1 << 32 | 0x1F7768AF,
        0x6564D5FD << 32 | 0xE549331A, 0x27943416 << 32 | 0x4CA30589,
        0xA9B6706F << 32 | 0xB8DFB2E3, 0xEB469184 << 32 | 0x11358470,
        0x2C57B3B8 << 32 | 0xEB0BDFC5, 0x6EA75253 << 32 | 0x42E1E956,
        0x72E3DAA0 << 32 | 0xAA188782, 0x30133B4B << 32 | 0x03F2B111,
        0xF7021977 << 32 | 0xF9CCEAA4, 0xB5F2F89C << 32 | 0x5026DC37,
        0x3BD0BCE5 << 32 | 0xA45A6B5D, 0x79205D0E << 32 | 0x0DB05DCE,
        0xBE317F32 << 32 | 0xF78E067B, 0xFCC19ED9 << 32 | 0x5E6430E8,
        0x86B86ED5 << 32 | 0x267CDBD3, 0xC4488F3E << 32 | 0x8F96ED40,
        0x0359AD02 << 32 | 0x75A8B6F5, 0x41A94CE9 << 32 | 0xDC428066,
        0xCF8B0890 << 32 | 0x283E370C, 0x8D7BE97B << 32 | 0x81D4019F,
        0x4A6ACB47 << 32 | 0x7BEA5A2A, 0x089A2AAC << 32 | 0xD2006CB9,
        0x14DEA25F << 32 | 0x3AF9026D, 0x562E43B4 << 32 | 0x931334FE,
        0x913F6188 << 32 | 0x692D6F4B, 0xD3CF8063 << 32 | 0xC0C759D8,
        0x5DEDC41A << 32 | 0x34BBEEB2, 0x1F1D25F1 << 32 | 0x9D51D821,
        0xD80C07CD << 32 | 0x676F8394, 0x9AFCE626 << 32 | 0xCE85B507,
    ];

    public static function make(string $value): string
    {
        // Final XOR: 0xFFFFFFFFFFFFFFFF
        $finalXor = (1 << 64) - 1;

        // Initial value: 0xFFFFFFFFFFFFFFFF
        $checksum = (1 << 64) - 1;

        for ($i = 0, $size = strlen($value); $i < $size; $i++) {
            $char = static::reflect8(ord($value[$i]));

            // CHECKSUM 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000
            //          ~~~~~~~~~~~~~~~~~~~ <- Right shift 56 bits
            // CHAR     1111 1111 1111 1111
            // XOR      -------------------
            //          The result is the index of the precomputed table
            $checksum = self::TABLE[($checksum >> 56 ^ $char) & 0xFF] ^ ($checksum << 8);
        }

        $checksum = static::reflect64($checksum);

        return sprintf('%u', $checksum ^ $finalXor);
    }

    private static function reflect8(int $value): int
    {
        return static::reflect($value, 8);
    }

    private static function reflect64(int $value): int
    {
        return static::reflect($value, 64);
    }

    private static function reflect(int $value, int $width): int
    {
        $reflected = 0;

        for ($i = 0; $i < $width; $i++) {
            if ($value & (1 << $i)) {
                $reflected |= 1 << ($width - 1 - $i);
            }
        }

        return $reflected;
    }
}
