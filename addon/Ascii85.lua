--====================================================================--
-- Module: Ascii 85 Encoding in Pure Lua
-- Author : Satheesh
-- 
-- License: MIT

-- Version : 1.1
-- ChangeLog
-- 1.1
-- 		Minor bug fixed 
--		All-Zero group will be represented as 'z'
--		
-- https://github.com/SatheeshJM/Ascii85-Encoding-in-Pure-Lua
--====================================================================--

local addonName, addonTable = ...

local floor = math.floor
local table_insert = table.insert

local function tostring(num)
    local tr = ''
    for x=1,4,1 do
        tr = string.char(num % 256)
        num = num / 256
    end
    return tr
end

local function decimalToBase85(num)
    local base = 85

    local final = {}
    while num > 0 do
        table_insert(final,1,num % base)
        num = floor(num / base)
    end

    while #final < 5 do
        table_insert(final,1,0)
    end

    return final
end

local function base85ToDecimal(b85)
    local base = 85

    local l = #b85
    local final = 0

    for i=l,1,-1 do
        local digit = b85[i]
        local val = digit * base^(l-i)
        final = final + val
    end

    return final
end


local function decimalToBinary(num)
    local base = 2
    local bits = 8

    local final = ""
    while num > 0 do
        final = "" ..  (num % base ) .. final
        num = floor(num / base)
    end

    local l = final:len()
    if l == 0 then
        final = "0"..final
    end

    while final:len()%8 ~=0 do
        final = "0"..final
    end

    return final
end


local function binaryToDecimal(bin)
    local base = 2

    local l = bin:len()
    local final = 0

    for i=l,1,-1 do
        local digit = string.byte(bin,i)
        local val = digit * base^(l-i)
        final = final + val
    end
    return final
end






local function decode(substr)

    local final = ""

    local l = substr:len()
    local combine = {}
    for i=1,l do
        local char = substr:sub(i,i)
        local byte = char:byte()
        byte = byte - 33
        combine[i] = byte
    end

    local num = base85ToDecimal(combine)
    local bin = decimalToBinary(num)

    while bin:len() < 32 do
        bin = "0"..bin
    end

    local l = bin:len()
    local split = 8
    for i=1,l,split do
        local sub = bin:sub(i,i+split-1)
        local byte = binaryToDecimal(sub)
        local char = tostring(byte)
        final = final..char
    end

    return final
end



local function ascii_encode(str)


    local final = ""

    local noOfZeros = 0
    while str:len()%4~=0 do
        noOfZeros =  noOfZeros + 1
        str = str.."\0"
    end

    local l = str:len()

    for i=1,l,4 do
        local sub = str:sub(i,i+3)
        final = final .. encode(sub)
    end

    final = final:sub(1,-noOfZeros-1)
    final = "<~"..final.."~>"
    return final

end


local function ascii_decode(str)

    local final = ""

    --str = str:sub(3,-3)
    str = str:gsub("z","!!!!!")

    local c = 5
    local noOfZeros = 0
    while str:len()%c~=0 do
        noOfZeros =  noOfZeros + 1
        str = str.."u"
    end


    local l = str:len()
    for i=1,l,c do
        local sub = str:sub(i,i+c-1)
        print("decode "..sub)
        final = final .. decode(sub)
    end

    final = final:sub(1,-noOfZeros-1)
    return final




end

addonTable.ascii85 = {encode = ascii_encode,decode = ascii_decode,bin2dec = binaryToDecimal}
