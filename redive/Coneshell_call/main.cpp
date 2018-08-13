/**
 * coneshell.dll caller
 * @author EAirPeter & esterTion
 * coneshell.dll is from (c)Cygames Inc.
 */

#define _CRT_SECURE_NO_WARNINGS
#define _WIN32_LEAN_AND_MEAN

#include <chrono>
#include <cstdint>
#include <cstdio>
#include <cstdlib>
#include <memory>
#include <string>
#include <string_view>
#include <thread>
#include <utility>

#include <Windows.h>

#include "sqlite3.h"

namespace {
    using namespace ::std;
    using namespace ::std::literals;

    using Int = int32_t;
    using Long = int64_t;
    using IntPtr = void *;
    using ByteArray = char *;

    inline void Assert(bool pred, int code) {
        if (!pred)
            exit(code);
    }

    struct ReturnValueChecker {
        inline void operator +(void *res) {
            Assert(res, 2);
        }
        inline void operator <(int res) {
            Assert(res >= 0, 2);
        }
        inline void operator =(int res) {
            Assert(!res, res);
        }
    } X;

    template<class Obj, class Del>
    decltype(auto) Wrap(Obj *ptr, Del del) {
        X + ptr;
        return unique_ptr<Obj, Del>(ptr, del);
    }

    string ReadAll(const char *file) {
        string res;
        auto f = Wrap(fopen(file, "rb"), &fclose);
        char buf[4096];
        while (!feof(f.get()))
            res.append(buf, fread(buf, 1, sizeof(buf), f.get()));
        return res;
    }

    void WriteAll(const char *file, string_view data) {
        auto f = Wrap(fopen(file, "wb"), &fclose);
        fwrite(data.data(), data.size(), 1, f.get());
    }

    constexpr int HexDigit(char ch) {
        if (ch < '0')
            exit(1);
        if (ch <= '9')
            return ch - '0';
        if (ch < 'A')
            exit(1);
        if (ch <= 'Z')
            return ch - 'A' + 10;
        if (ch < 'a')
            exit(1);
        if (ch <= 'z')
            return ch - 'a' + 10;
        exit(1);
    }

    constexpr char Hex2Byte(const char hi, const char lo) {
        return (char) (HexDigit(hi) << 4 | HexDigit(lo));
    }

    inline string Hex2Bin(string_view hex) {
        if (hex.size() % 2 != 0)
            exit(-1);
        string res;
        for (size_t i = 0; i < hex.size(); i += 2)
            res += Hex2Byte(hex[i], hex[i + 1]);
        return res;
    }

    inline int IncorrectUsage() {
        fputs(
            "\n"
            "Incorrect usage\n"
            "    -cdb            <in> <out>  unpack cdb\n"
            "    -pack-<udid>    <in> <out>  pack request body from json\n"
            "    -unpack-<udid>  <in> <out>  unpack response body to json\n",
            stderr
        );
        this_thread::sleep_for(3s);
        return 1;
    }

}

int main(int argc, const char *argv[]) {
    if (argc != 4)
        return IncorrectUsage();

    auto h = Wrap(LoadLibraryA("coneshell.dll"), &FreeLibrary);
    auto *_fx00 = (IntPtr(*)()) GetProcAddress(h.get(), "_fx00");
    auto *_a = (IntPtr(*)()) _fx00(); // NextFunctionPointer()
    auto *_e = (Int(*)(IntPtr, ByteArray, Int, ByteArray, Int)) _a(); // Pack(IntPtr out, ByteArray body, int bodyLen, ByteArray iv, int unk)
    auto *_g = (Int(*)(IntPtr, ByteArray, Int)) _a(); // Unpack(IntPtr out, ByteArray body, int bodyLen)
    auto *_h = (Int(*)(IntPtr, Int, IntPtr, Int)) _a(); // DecompressUnpacked(IntPtr out, int decompressedSize, IntPtr body, int bodyLen)
    auto *_c = (void(*)()) _a(); // ResetContext()
    auto *_i = (IntPtr(*)(ByteArray, Long, ByteArray)) _a(); // OpenCustomVFS(ByteArray cdb, int cdbSize, ByteArray dbName)
    auto *_j = (void(*)(IntPtr)) _a(); // CloseVFS(IntPtr vfsHandle)
    auto *_b = (Int(*)(ByteArray, ByteArray)) _a(); // InitializeContext(udid, key)
    auto *_d = (Int(*)(Int, Int)) _a(); // GetPackedSize(int bodySize)
    auto *_f = (Int(*)(Int)) _a(); // GetUnpackedSize(int bodySize)

    string_view mode = argv[1];
    if (mode == "-cdb") {
        auto cdb = ReadAll(argv[2]);
        char name[] {"master.mdb"};
        // prepare cdb to vfs
        auto vfs = _i(cdb.data(), (Long) cdb.size(), name);
        X = sqlite3_vfs_register((sqlite3_vfs *) vfs, 0);
        sqlite3 *psrc;
        X = sqlite3_open_v2(name, &psrc, SQLITE_OPEN_READONLY, name);
        auto src = Wrap(psrc, &sqlite3_close);
        sqlite3 *pdst;
        X = sqlite3_open(argv[3], &pdst);
        auto dst = Wrap(pdst, &sqlite3_close);
        auto pbk = sqlite3_backup_init(pdst, "main", psrc, "main");
        X + pbk;
        auto bk = Wrap(pbk, &sqlite3_backup_finish);
        auto res = sqlite3_backup_step(pbk, -1);
        if (res != SQLITE_DONE)
            return res;
        return 0;
    }
    else if (mode.substr(0, 7) == "-unpack") {
        //string udid = "edcadba12a674a089107d8065a031742";
        auto udid = mode.substr(8);
        auto udidHex = Hex2Bin(udid);
        char zeros[32] {};
        X = _b(udidHex.data(), zeros);

        // pack once before unpack
        string body = "{}";
        auto packedLen = _d((int) body.size(), 0);
        string packed((size_t) packedLen, '\0');
        X < _e(packed.data(), body.data(), (int) body.size(), zeros, 0);

        // unpack
        auto encrypted = ReadAll(argv[2]);
        auto unpackedLen = _f((int) encrypted.size());
        string unpacked((size_t) unpackedLen, '\0');
        X < _g(unpacked.data(), encrypted.data(), (int) encrypted.size());

        // uncompress?
        auto uncompressedSize = unpacked[0] | unpacked[1] << 8 | unpacked[2] << 16 | unpacked[3] << 24;
        if (uncompressedSize > 0) {
            string json((size_t) uncompressedSize, '\0');
            X < _h(json.data(), uncompressedSize, unpacked.data() + 4, unpackedLen - 4);
            WriteAll(argv[3], json);
        }
        else {
            WriteAll(argv[3], string_view(unpacked).substr(4));
        }
        return 0;
    }
    else if (mode.substr(0, 5) == "-pack") {
        //string udid = "edcadba12a674a089107d8065a031742";
        auto udid = mode.substr(6);
        auto udidHex = Hex2Bin(udid);
        char zeros[32] {};
        X = _b(udidHex.data(), zeros);

        auto body = ReadAll(argv[2]);
        auto packedLen = _d((int) body.size(), 0);
        string packed((size_t) packedLen, '\0');
        auto res = _e(packed.data(), body.data(), (int) body.size(), zeros, 0);
        X < res;
        packed.resize((size_t) res);
        WriteAll(argv[3], packed);
        return 0;
    }
    return IncorrectUsage();
}
